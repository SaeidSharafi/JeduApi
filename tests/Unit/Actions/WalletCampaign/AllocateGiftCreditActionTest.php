<?php

use App\Actions\Admin\Wallet\AllocateGiftCreditAction;
use App\Actions\Wallet\RecordWalletTransactionAction;
use App\Data\Admin\Wallet\AllocateGiftCreditData;
use App\Enums\Wallet\CampaignTypeEnum;
use App\Enums\Wallet\TransactionSourceEnum;
use App\Enums\Wallet\TransactionTypeEnum;
use App\Events\Wallet\WalletGiftCreditAllocatedEvent;
use App\Exceptions\CustomValidationException;
use App\Models\Staff;
use App\Models\User;
use App\Models\WalletCampaign;
use App\Models\WalletTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\AuthTestTrait;

uses(AuthTestTrait::class);

beforeEach(function () {
    $this->customer = User::factory()->create();
    $this->user = Staff::factory()->create();

    $this->campaign = WalletCampaign::factory()->create([
        'name' => 'Test Gift Campaign',
        'type' => CampaignTypeEnum::WELCOME_GIFT,
        'amount' => 50000,
        'is_active' => true,
        'usage_limit_total' => 1000,
        'usage_limit_per_user' => 1,
        'total_usage_count' => 0,
        'starts_at' => Carbon::now()->subDay(),
        'ends_at' => Carbon::now()->addMonth(),
        'created_by' => $this->user->id,
    ]);

    $this->mockRecordAction = $this->mock(RecordWalletTransactionAction::class);
    $this->action = new AllocateGiftCreditAction($this->mockRecordAction);

    Event::fake([
        WalletGiftCreditAllocatedEvent::class
    ]);
});

it('successfully allocates gift credit to user', function () {
    $data = new AllocateGiftCreditData(
        user_id: $this->customer->id,
        reason: 'Welcome gift allocation',
        metadata: ['admin_id' => $this->user->id]
    );

    $mockTransaction = WalletTransaction::factory()->make([
        'id' => 1,
        'user_id' => $this->customer->id,
        'type' => TransactionTypeEnum::GIFT,
        'amount' => 50000,
    ]);

    $this->mockRecordAction
        ->shouldReceive('execute')
        ->once()
        ->withArgs(function ($transactionData) {
            return $transactionData->user_id === $this->customer->id
                && $transactionData->type === TransactionTypeEnum::GIFT
                && $transactionData->amount === 50000
                && $transactionData->source_type === TransactionSourceEnum::CAMPAIGN
                && $transactionData->source_id === $this->campaign->id
                && $transactionData->description === 'Welcome gift allocation';
        })
        ->andReturn($mockTransaction);

    $result = $this->action->handle($data, $this->campaign);

    expect($result)->toBeInstanceOf(WalletTransaction::class);
    expect($this->campaign->fresh()->total_usage_count)->toBe(1);

    Event::assertDispatched(WalletGiftCreditAllocatedEvent::class);
});

it('returns existing transaction for idempotent requests', function () {
    // Create existing transaction
    $existingTransaction = WalletTransaction::factory()->create([
        'user_id' => $this->customer->id,
        'type' => TransactionTypeEnum::GIFT,
        'source_type' => TransactionSourceEnum::CAMPAIGN,
        'source_id' => $this->campaign->id,
        'amount' => 50000,
    ]);

    $data = new AllocateGiftCreditData(

        user_id: $this->customer->id,
        reason: 'Duplicate allocation attempt'
    );

    $this->mockRecordAction->shouldNotReceive('execute');

    expect(fn() => $this->action->handle($data, $this->campaign))
        ->toThrow(CustomValidationException::class);

    expect($this->campaign->fresh()->total_usage_count)->toBe(0); // Should not increment

    Event::assertNotDispatched(WalletGiftCreditAllocatedEvent::class);
});

it('throws exception when user has no wallet', function () {
    $userWithoutWallet = User::factory()->create();
    $userWithoutWallet->wallet->delete();

    $data = new AllocateGiftCreditData(

        user_id: $userWithoutWallet->id,
        reason: 'Test allocation'
    );

    $this->mockRecordAction->shouldNotReceive('execute');

    expect(fn() => $this->action->handle($data, $this->campaign))
        ->toThrow(\Exception::class, __('validation.custom.wallet_not_found'));
});

it('throws exception when campaign is inactive', function () {
    $this->campaign->update(['is_active' => false]);

    $data = new AllocateGiftCreditData(

        user_id: $this->customer->id,
        reason: 'Test allocation'
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

    $data = new AllocateGiftCreditData(

        user_id: $this->customer->id,
        reason: 'Test allocation'
    );

    $this->mockRecordAction->shouldNotReceive('execute');

    expect(fn() => $this->action->handle($data, $this->campaign))
        ->toThrow(\Exception::class, __('validation.custom.campaign_expired'));
});

it('throws exception when campaign usage limit reached', function () {
    $this->campaign->update([
        'usage_limit_total' => 5,
        'total_usage_count' => 5,
    ]);

    $data = new AllocateGiftCreditData(

        user_id: $this->customer->id,
        reason: 'Test allocation'
    );

    $this->mockRecordAction->shouldNotReceive('execute');

    expect(fn() => $this->action->handle($data, $this->campaign))
        ->toThrow(\Exception::class, __('validation.custom.usage_limit_reached'));
});

it('throws exception when user has reached per-user limit', function () {
    // Create existing transaction to simulate user limit reached
    WalletTransaction::factory()->create([
        'user_id' => $this->customer->id,
        'source_type' => TransactionSourceEnum::CAMPAIGN,
        'source_id' => $this->campaign->id,
        'type' => TransactionTypeEnum::GIFT,
    ]);

    $data = new AllocateGiftCreditData(

        user_id: $this->customer->id,
        reason: 'Second allocation attempt'
    );

    $this->mockRecordAction->shouldNotReceive('execute');

    expect(fn() => $this->action->handle($data, $this->campaign))
        ->toThrow(\Exception::class, __('validation.custom.already_claimed'));
});

it('uses default description when reason is not provided', function () {
    $data = new AllocateGiftCreditData(

        user_id: $this->customer->id,
        reason: null
    );

    $mockTransaction = WalletTransaction::factory()->make([
        'id' => 1,
        'user_id' => $this->customer->id,
    ]);

    $this->mockRecordAction
        ->shouldReceive('execute')
        ->once()
        ->withArgs(function ($transactionData) {
            return str_contains($transactionData->description, 'Test Gift Campaign');
        })
        ->andReturn($mockTransaction);

    $this->action->handle($data, $this->campaign);
});

it('includes correct metadata in transaction', function () {
    $data = new AllocateGiftCreditData(

        user_id: $this->customer->id,
        reason: 'Test allocation',
        metadata: ['source' => 'admin_panel', 'notes' => 'Manual allocation']
    );

    $mockTransaction = WalletTransaction::factory()->make([
        'id' => 1,
        'user_id' => $this->customer->id,
    ]);

    $this->mockRecordAction
        ->shouldReceive('execute')
        ->once()
        ->withArgs(function ($transactionData) {
            $metadata = $transactionData->metadata;
            return $metadata['campaign_name'] === 'Test Gift Campaign'
                && $metadata['campaign_type'] === CampaignTypeEnum::WELCOME_GIFT
                && $metadata['allocation_reason'] === 'Test allocation'
                && $metadata['source'] === 'admin_panel'
                && $metadata['notes'] === 'Manual allocation';
        })
        ->andReturn($mockTransaction);

    $this->action->handle($data, $this->campaign);
});

it('throws exception for non-existent user', function () {
    $data = new AllocateGiftCreditData(

        user_id: 99999,
        reason: 'Test allocation'
    );

    expect(fn() => $this->action->handle($data, $this->campaign))
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});
