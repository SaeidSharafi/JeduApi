<?php

use App\Actions\Admin\WalletCampaign\ProcessCampaignBonusAction;
use App\Actions\Wallet\RecordWalletTransactionAction;
use App\Data\Admin\Wallet\ProcessCampaignBonusData;
use App\Enums\Wallet\CampaignTypeEnum;
use App\Enums\Wallet\TransactionSourceEnum;
use App\Enums\Wallet\TransactionTypeEnum;
use App\Events\Wallet\WalletBonusProcessedEvent;
use App\Models\Staff;
use App\Models\User;
use App\Models\WalletCampaign;
use App\Models\WalletTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\AuthTestTrait;

uses(AuthTestTrait::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->staff = Staff::factory()->create();

    $this->campaign = WalletCampaign::factory()->create([
        'name' => 'Referral Bonus Campaign',
        'type' => CampaignTypeEnum::REFERRAL_BONUS,
        'amount' => 25000,
        'is_active' => true,
        'usage_limit_total' => 500,
        'usage_limit_per_user' => 3,
        'total_usage_count' => 0,
        'starts_at' => Carbon::now()->subDay(),
        'ends_at' => Carbon::now()->addMonth(),
        'created_by' => $this->staff->id,
    ]);

    $this->mockRecordAction = $this->mock(RecordWalletTransactionAction::class);
    $this->action = new ProcessCampaignBonusAction($this->mockRecordAction);

    Event::fake([
        WalletBonusProcessedEvent::class,

    ]);
});

it('successfully processes campaign bonus for user', function () {
    $data = new ProcessCampaignBonusData(
        user_id: $this->user->id,
        trigger_event: 'referral_completed',
        metadata: [
            'referred_user_id' => 456,
            'referral_code' => 'REF123'
        ]
    );

    $mockTransaction = WalletTransaction::factory()->make([
        'id' => 1,
        'user_id' => $this->user->id,
        'type' => TransactionTypeEnum::BONUS,
        'amount' => 25000,
    ]);

    $this->mockRecordAction
        ->shouldReceive('execute')
        ->once()
        ->withArgs(function ($transactionData) {
            return $transactionData->user_id === $this->user->id
                && $transactionData->type === TransactionTypeEnum::BONUS
                && $transactionData->amount === 25000
                && $transactionData->source_type === TransactionSourceEnum::CAMPAIGN
                && $transactionData->source_id === $this->campaign->id
                && str_contains($transactionData->description, 'Referral Bonus Campaign');
        })
        ->andReturn($mockTransaction);

    $result = $this->action->handle($data,$this->campaign);

    expect($result)->toBeInstanceOf(WalletTransaction::class);
    expect($this->campaign->fresh()->total_usage_count)->toBe(1);

    Event::assertDispatched(WalletBonusProcessedEvent::class);
});

it('returns existing transaction for same trigger event (idempotency)', function () {
    // Create existing transaction with same trigger event
    $existingTransaction = WalletTransaction::factory()->create([
        'user_id' => $this->user->id,
        'type' => TransactionTypeEnum::BONUS,
        'source_type' => TransactionSourceEnum::CAMPAIGN,
        'source_id' => $this->campaign->id,
        'amount' => 25000,
        'metadata' => ['trigger_event' => 'first_purchase']
    ]);

    $data = new ProcessCampaignBonusData(
        user_id: $this->user->id,
        trigger_event: 'first_purchase'
    );

    $this->mockRecordAction->shouldNotReceive('execute');

    $result = $this->action->handle($data, $this->campaign);

    expect($result->id)->toBe($existingTransaction->id);
    expect($this->campaign->fresh()->total_usage_count)->toBe(0); // Should not increment

    Event::assertNotDispatched(WalletBonusProcessedEvent::class);
});

it('processes manual bonus without trigger event', function () {
    $data = new ProcessCampaignBonusData(
        user_id: $this->user->id,
        trigger_event: null, // Manual trigger
        metadata: ['admin_reason' => 'Customer service bonus']
    );

    $mockTransaction = WalletTransaction::factory()->make([
        'id' => 1,
        'user_id' => $this->user->id,
        'type' => TransactionTypeEnum::BONUS,
    ]);

    $this->mockRecordAction
        ->shouldReceive('execute')
        ->once()
        ->withArgs(function ($transactionData) {
            return $transactionData->metadata['trigger_event'] === null
                && $transactionData->metadata['admin_reason'] === 'Customer service bonus';
        })
        ->andReturn($mockTransaction);

    $result = $this->action->handle($data, $this->campaign);

    expect($result)->toBeInstanceOf(WalletTransaction::class);
});

it('throws exception when user has no wallet', function () {
    $userWithoutWallet = User::factory()->create();
    $userWithoutWallet->wallet->delete();

    $data = new ProcessCampaignBonusData(
        user_id: $userWithoutWallet->id,
        trigger_event: 'test_event'
    );

    $this->mockRecordAction->shouldNotReceive('execute');

    expect(fn() => $this->action->handle($data, $this->campaign))
        ->toThrow(\Exception::class, __('validation.custom.wallet_not_found'));
});

it('throws exception when campaign is inactive', function () {
    $this->campaign->update(['is_active' => false]);

    $data = new ProcessCampaignBonusData(
        user_id: $this->user->id,
        trigger_event: 'test_event'
    );

    $this->mockRecordAction->shouldNotReceive('execute');

    expect(fn() => $this->action->handle($data, $this->campaign))
        ->toThrow(\Exception::class, __('validation.custom.campaign_not_active'));
});

it('throws exception when campaign is expired', function () {
    $this->campaign->update([
        'starts_at' => Carbon::now()->subMonth(),
        'ends_at' => Carbon::now()->subDay(),
    ]);

    $data = new ProcessCampaignBonusData(
        user_id: $this->user->id,
        trigger_event: 'test_event'
    );

    $this->mockRecordAction->shouldNotReceive('execute');

    expect(fn() => $this->action->handle($data, $this->campaign))
        ->toThrow(\Exception::class, __('validation.custom.campaign_expired'));
});

it('throws exception when total usage limit is reached', function () {
    $this->campaign->update([
        'usage_limit_total' => 10,
        'total_usage_count' => 10,
    ]);

    $data = new ProcessCampaignBonusData(
        user_id: $this->user->id,
        trigger_event: 'test_event'
    );

    $this->mockRecordAction->shouldNotReceive('execute');

    expect(fn() => $this->action->handle($data, $this->campaign))
        ->toThrow(\Exception::class, __('validation.custom.usage_limit_reached'));
});

it('throws exception when user has reached per-user limit', function () {
    // Create transactions to reach per-user limit (3)
    WalletTransaction::factory()->count(3)->create([
        'user_id' => $this->user->id,
        'source_type' => TransactionSourceEnum::CAMPAIGN,
        'source_id' => $this->campaign->id,
        'type' => TransactionTypeEnum::BONUS,
        'metadata' => ['trigger_event' => 'different_events']
    ]);

    $data = new ProcessCampaignBonusData(
        user_id: $this->user->id,
        trigger_event: 'new_event'
    );

    $this->mockRecordAction->shouldNotReceive('execute');

    expect(fn() => $this->action->handle($data, $this->campaign))
        ->toThrow(\Exception::class, __('validation.custom.already_claimed'));
});

it('uses localized description with trigger event', function () {
    $data = new ProcessCampaignBonusData(
        user_id: $this->user->id,
        trigger_event: 'milestone_reached'
    );

    $mockTransaction = WalletTransaction::factory()->make(['id' => 1]);

    $this->mockRecordAction
        ->shouldReceive('execute')
        ->once()
        ->withArgs(function ($transactionData) {
            return str_contains($transactionData->description, 'Referral Bonus Campaign')
                && str_contains($transactionData->description, 'milestone_reached');
        })
        ->andReturn($mockTransaction);

    $this->action->handle($data, $this->campaign);
});

it('uses manual trigger description when no trigger event provided', function () {
    $data = new ProcessCampaignBonusData(
        user_id: $this->user->id,
        trigger_event: null
    );

    $mockTransaction = WalletTransaction::factory()->make(['id' => 1]);

    $this->mockRecordAction
        ->shouldReceive('execute')
        ->once()
        ->withArgs(function ($transactionData) {
            return str_contains($transactionData->description, __('wallet.campaign.manual_trigger'));
        })
        ->andReturn($mockTransaction);

    $this->action->handle($data, $this->campaign);
});

it('includes correct metadata in transaction', function () {
    $data = new ProcessCampaignBonusData(
        user_id: $this->user->id,
        trigger_event: 'birthday_celebration',
        metadata: [
            'birth_date' => '1990-01-01',
            'bonus_multiplier' => 2.0
        ]
    );

    $mockTransaction = WalletTransaction::factory()->make(['id' => 1]);

    $this->mockRecordAction
        ->shouldReceive('execute')
        ->once()
        ->withArgs(function ($transactionData) {
            $metadata = $transactionData->metadata;
            return $metadata['campaign_name'] === 'Referral Bonus Campaign'
                && $metadata['campaign_type'] === CampaignTypeEnum::REFERRAL_BONUS
                && $metadata['trigger_event'] === 'birthday_celebration'
                && $metadata['birth_date'] === '1990-01-01'
                && $metadata['bonus_multiplier'] === 2.0;
        })
        ->andReturn($mockTransaction);

    $this->action->handle($data, $this->campaign);
});

it('allows multiple bonuses for different trigger events', function () {
    // First bonus
    $data1 = new ProcessCampaignBonusData(
        user_id: $this->user->id,
        trigger_event: 'first_purchase'
    );

    $mockTransaction1 = WalletTransaction::factory()->make(['id' => 1]);
    $this->mockRecordAction
        ->shouldReceive('execute')
        ->once()
        ->andReturn($mockTransaction1);

    $this->action->handle($data1, $this->campaign);

    // Second bonus with different trigger event
    $data2 = new ProcessCampaignBonusData(
        user_id: $this->user->id,
        trigger_event: 'referral_completed'
    );

    $mockTransaction2 = WalletTransaction::factory()->make(['id' => 2]);
    $this->mockRecordAction
        ->shouldReceive('execute')
        ->once()
        ->andReturn($mockTransaction2);

    $result2 = $this->action->handle($data2, $this->campaign);

    expect($result2)->toBeInstanceOf(WalletTransaction::class);
    expect($this->campaign->fresh()->total_usage_count)->toBe(2);
});

it('throws exception for non-existent user', function () {
    $data = new ProcessCampaignBonusData(
        user_id: 99999,
        trigger_event: 'test_event'
    );

    expect(fn() => $this->action->handle($data, $this->campaign))
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});
