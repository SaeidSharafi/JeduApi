<?php

declare(strict_types=1);

use App\Actions\Admin\WalletCampaign\TriggerCampaignAllocationAction;
use App\Actions\Wallet\RecordWalletTransactionAction;
use App\Data\Admin\WalletCampaign\TriggerCampaignAllocationData;
use App\Enums\Wallet\TransactionSourceEnum;
use App\Enums\Wallet\TransactionTypeEnum;
use App\Enums\WalletCampaign\CampaignTriggerEventEnum;
use App\Enums\WalletCampaign\CampaignTriggerTypeEnum;
use App\Enums\WalletCampaign\CampaignTypeEnum;
use App\Events\Wallet\WalletCampaignAllocationTriggeredEvent;
use App\Exceptions\CustomValidationException;
use App\Models\Staff;
use App\Models\User;
use App\Models\WalletCampaign;
use App\Models\WalletTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\Support\Traits\AuthTestTrait;

uses(AuthTestTrait::class);

beforeEach(function (): void {
    $this->customer = User::factory()->create();
    $this->user     = Staff::factory()->create();

    $this->campaign = WalletCampaign::factory()->create([
        'name'                 => 'Test Campaign',
        'type'                 => CampaignTypeEnum::MANUAL_ALLOCATION,
        'amount'               => 50000,
        'is_active'            => true,
        'usage_limit_total'    => 1000,
        'usage_limit_per_user' => 2,
        'total_usage_count'    => 0,
        'starts_at'            => Carbon::now()->subDay(),
        'ends_at'              => Carbon::now()->addMonth(),
        'created_by'           => $this->user->id,
    ]);

    $this->mockRecordAction = $this->mock(RecordWalletTransactionAction::class);
    $this->action           = new TriggerCampaignAllocationAction($this->mockRecordAction);

    Event::fake([
        WalletCampaignAllocationTriggeredEvent::class,
    ]);
});

describe('TriggerCampaignAllocationAction', function (): void {
    it('processes a manual allocation with the supplied request key', function (): void {
        $data = new TriggerCampaignAllocationData(
            trigger_type: CampaignTriggerTypeEnum::MANUAL,
            trigger_event: null,
            reason: 'Admin manual allocation',
            metadata: ['admin_id' => $this->user->id],
            idempotency_key: 'submit-uuid-1',
        );

        $mockTransaction = WalletTransaction::factory()->make([
            'id'          => 1,
            'user_id'     => $this->customer->id,
            'type'        => TransactionTypeEnum::GIFT,
            'amount'      => $this->campaign->amount,
            'source_type' => TransactionSourceEnum::CAMPAIGN,
            'source_id'   => $this->campaign->id,
        ]);

        $this->mockRecordAction
            ->expects('execute')
            ->once()
            ->withArgs(fn ($transactionData): bool => $transactionData->idempotency_key === 'submit-uuid-1'
                && $transactionData->metadata['trigger_type']                           === 'manual'
                && $transactionData->metadata['trigger_event']                          === null)
            ->andReturn($mockTransaction);

        $result = $this->action->handle($data, $this->customer, $this->campaign);

        expect($result)->toBe($mockTransaction);
        expect($this->campaign->fresh()->total_usage_count)->toBe(1);

        Event::assertDispatched(WalletCampaignAllocationTriggeredEvent::class, function ($event): bool {
            return $event->triggerType === 'manual';
        });
    });

    it('processes an event-based allocation with the business key', function (): void {
        $data = new TriggerCampaignAllocationData(
            trigger_type: CampaignTriggerTypeEnum::EVENT,
            trigger_event: CampaignTriggerEventEnum::PROFILE_COMPLETED,
            metadata: ['profile_completed_at' => now()->toISOString()],
        );

        $mockTransaction = WalletTransaction::factory()->make([
            'id'          => 1,
            'user_id'     => $this->customer->id,
            'type'        => TransactionTypeEnum::GIFT,
            'amount'      => $this->campaign->amount,
            'source_type' => TransactionSourceEnum::CAMPAIGN,
            'source_id'   => $this->campaign->id,
        ]);

        $this->mockRecordAction
            ->expects('execute')
            ->once()
            ->withArgs(fn ($transactionData): bool => $transactionData->idempotency_key === sprintf(
                'wallet-campaign:%d:user:%d:trigger:event:event:profile_completed:occurrence:none',
                $this->campaign->id,
                $this->customer->id
            ))
            ->andReturn($mockTransaction);

        $result = $this->action->handle($data, $this->customer, $this->campaign);

        expect($result)->toBe($mockTransaction);
        expect($this->campaign->fresh()->total_usage_count)->toBe(1);

        Event::assertDispatched(WalletCampaignAllocationTriggeredEvent::class, function ($event): bool {
            return $event->triggerType === 'event';
        });
    });

    it('allocates again for repeated manual grants', function (): void {
        $data = new TriggerCampaignAllocationData(
            trigger_type: CampaignTriggerTypeEnum::MANUAL,
            trigger_event: null,
            reason: 'Repeat grant',
        );

        $firstTransaction  = WalletTransaction::factory()->make(['id' => 1, 'user_id' => $this->customer->id]);
        $secondTransaction = WalletTransaction::factory()->make(['id' => 2, 'user_id' => $this->customer->id]);

        $this->mockRecordAction
            ->expects('execute')
            ->twice()
            ->andReturn($firstTransaction, $secondTransaction);

        $first  = $this->action->handle($data, $this->customer, $this->campaign);
        $second = $this->action->handle($data, $this->customer, $this->campaign);

        expect($first->id)->toBe(1)
            ->and($second->id)->toBe(2)
            ->and($this->campaign->fresh()->total_usage_count)->toBe(2);
    });

    it('prevents duplicate event-based allocations for the same trigger event', function (): void {
        $existingTransaction = WalletTransaction::factory()->create([
            'user_id'     => $this->customer->id,
            'type'        => TransactionTypeEnum::GIFT,
            'source_type' => TransactionSourceEnum::CAMPAIGN,
            'source_id'   => $this->campaign->id,
            'metadata'    => ['trigger_event' => 'profile_completed'],
        ]);

        $data = new TriggerCampaignAllocationData(
            trigger_type: CampaignTriggerTypeEnum::EVENT,
            trigger_event: CampaignTriggerEventEnum::PROFILE_COMPLETED,
        );

        $this->mockRecordAction->shouldNotReceive('execute');

        $result = $this->action->handle($data, $this->customer, $this->campaign);

        expect($result->id)->toBe($existingTransaction->id);
        expect($this->campaign->fresh()->total_usage_count)->toBe(0);
    });

    it('allows different event-based allocations for different trigger events', function (): void {
        WalletTransaction::factory()->create([
            'user_id'     => $this->customer->id,
            'type'        => TransactionTypeEnum::GIFT,
            'source_type' => TransactionSourceEnum::CAMPAIGN,
            'source_id'   => $this->campaign->id,
            'metadata'    => ['trigger_event' => 'profile_completed'],
        ]);

        $data = new TriggerCampaignAllocationData(
            trigger_type: CampaignTriggerTypeEnum::EVENT,
            trigger_event: CampaignTriggerEventEnum::PAYMENT_COMPLETED,
        );

        $mockTransaction = WalletTransaction::factory()->make([
            'id'          => 2,
            'user_id'     => $this->customer->id,
            'type'        => TransactionTypeEnum::GIFT,
            'amount'      => $this->campaign->amount,
            'source_type' => TransactionSourceEnum::CAMPAIGN,
            'source_id'   => $this->campaign->id,
        ]);

        $this->mockRecordAction
            ->expects('execute')
            ->once()
            ->andReturn($mockTransaction);

        $result = $this->action->handle($data, $this->customer, $this->campaign);

        expect($result)->toBe($mockTransaction);
        expect($this->campaign->fresh()->total_usage_count)->toBe(1);
    });

    it('allows the same recurring event for a new occurrence', function (): void {
        WalletTransaction::factory()->create([
            'user_id'     => $this->customer->id,
            'type'        => TransactionTypeEnum::GIFT,
            'source_type' => TransactionSourceEnum::CAMPAIGN,
            'source_id'   => $this->campaign->id,
            'metadata'    => ['trigger_event' => 'birthday_sweep', 'occurrence' => '1404'],
        ]);

        $data = new TriggerCampaignAllocationData(
            trigger_type: CampaignTriggerTypeEnum::EVENT,
            trigger_event: CampaignTriggerEventEnum::BIRTHDAY_SWEEP,
            trigger_occurrence: '1405',
        );

        $mockTransaction = WalletTransaction::factory()->make(['id' => 3, 'user_id' => $this->customer->id]);

        $this->mockRecordAction
            ->expects('execute')
            ->once()
            ->andReturn($mockTransaction);

        $result = $this->action->handle($data, $this->customer, $this->campaign);

        expect($result)->toBe($mockTransaction);
    });

    it('throws exception when user does not have wallet', function (): void {
        $userWithoutWallet = User::factory()->create();
        $userWithoutWallet->wallet->delete();
        $userWithoutWallet->refresh();

        $data = new TriggerCampaignAllocationData(
            trigger_type: CampaignTriggerTypeEnum::MANUAL,
            trigger_event: null,
        );

        expect(fn () => $this->action->handle($data, $userWithoutWallet, $this->campaign))
            ->toThrow(CustomValidationException::class);
    });

    it('throws exception when campaign is inactive', function (): void {
        $this->campaign->update(['is_active' => false]);

        $data = new TriggerCampaignAllocationData(
            trigger_type: CampaignTriggerTypeEnum::MANUAL,
            trigger_event: null,
        );

        expect(fn () => $this->action->handle($data, $this->customer, $this->campaign))
            ->toThrow(CustomValidationException::class);
    });

    it('throws exception when campaign has expired', function (): void {
        $this->campaign->update([
            'starts_at' => Carbon::now()->subMonth(),
            'ends_at'   => Carbon::now()->subDay(),
        ]);

        $data = new TriggerCampaignAllocationData(
            trigger_type: CampaignTriggerTypeEnum::MANUAL,
            trigger_event: null,
        );

        expect(fn () => $this->action->handle($data, $this->customer, $this->campaign))
            ->toThrow(CustomValidationException::class);
    });

    it('throws exception when campaign has reached total usage limit', function (): void {
        $this->campaign->update([
            'usage_limit_total' => 5,
            'total_usage_count' => 5,
        ]);

        $data = new TriggerCampaignAllocationData(
            trigger_type: CampaignTriggerTypeEnum::MANUAL,
            trigger_event: null,
        );

        expect(fn () => $this->action->handle($data, $this->customer, $this->campaign))
            ->toThrow(CustomValidationException::class);
    });

    it('throws exception when user has reached their usage limit', function (): void {
        $this->campaign->update(['usage_limit_per_user' => 1]);

        WalletTransaction::factory()->create([
            'user_id'     => $this->customer->id,
            'source_type' => TransactionSourceEnum::CAMPAIGN,
            'source_id'   => $this->campaign->id,
        ]);

        $data = new TriggerCampaignAllocationData(
            trigger_type: CampaignTriggerTypeEnum::MANUAL,
            trigger_event: null,
        );

        expect(fn () => $this->action->handle($data, $this->customer, $this->campaign))
            ->toThrow(CustomValidationException::class);
    });

    it('reuses the transaction for a replayed request key', function (): void {
        $existingTransaction = WalletTransaction::factory()->create([
            'user_id'         => $this->customer->id,
            'type'            => TransactionTypeEnum::GIFT,
            'amount'          => $this->campaign->amount,
            'source_type'     => TransactionSourceEnum::CAMPAIGN,
            'source_id'       => $this->campaign->id,
            'idempotency_key' => 'submit-uuid-2',
        ]);

        $data = new TriggerCampaignAllocationData(
            trigger_type: CampaignTriggerTypeEnum::MANUAL,
            trigger_event: null,
            reason: 'Retry',
            idempotency_key: 'submit-uuid-2',
        );

        $this->mockRecordAction->shouldNotReceive('execute');

        expect($this->action->handle($data, $this->customer, $this->campaign)->id)
            ->toBe($existingTransaction->id);
    });
});
