<?php

declare(strict_types=1);

use App\Actions\Admin\WalletCampaign\TriggerCampaignAllocationAction;
use App\Actions\Wallet\RecordWalletTransactionAction;
use App\Data\Admin\WalletCampaign\TriggerCampaignAllocationData;
use App\Enums\Wallet\TransactionSourceEnum;
use App\Enums\Wallet\TransactionTypeEnum;
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
        'type'                 => CampaignTypeEnum::WELCOME_GIFT,
        'amount'               => 50000,
        'is_active'            => true,
        'usage_limit_total'    => 1000,
        'usage_limit_per_user' => 2, // Allow multiple allocations for different trigger types
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
    it('successfully processes manual campaign allocation', function (): void {
        $data = new TriggerCampaignAllocationData(
            trigger_type: 'manual',
            trigger_event: null,
            reason: 'Admin manual allocation',
            metadata: ['admin_id' => $this->user->id]
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
            ->withArgs(function ($transactionData): bool {
                return $transactionData->idempotency_key === sprintf(
                    'wallet-campaign:%d:user:%d:trigger:manual:event:manual',
                    $this->campaign->id,
                    $this->customer->id
                );
            })
            ->andReturn($mockTransaction);

        $result = $this->action->handle($data, $this->customer, $this->campaign);

        expect($result)->toBe($mockTransaction);
        expect($this->campaign->fresh()->total_usage_count)->toBe(1);

        Event::assertDispatched(WalletCampaignAllocationTriggeredEvent::class, function ($event): bool {
            return $event->triggerType === 'manual';
        });
    });

    it('successfully processes event-based campaign allocation', function (): void {
        $data = new TriggerCampaignAllocationData(
            trigger_type: 'event',
            trigger_event: 'user_registration',
            metadata: ['registration_date' => now()]
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
            ->withArgs(function ($transactionData): bool {
                return $transactionData->idempotency_key === sprintf(
                    'wallet-campaign:%d:user:%d:trigger:event:event:user_registration',
                    $this->campaign->id,
                    $this->customer->id
                );
            })
            ->andReturn($mockTransaction);

        $result = $this->action->handle($data, $this->customer, $this->campaign);

        expect($result)->toBe($mockTransaction);
        expect($this->campaign->fresh()->total_usage_count)->toBe(1);

        Event::assertDispatched(WalletCampaignAllocationTriggeredEvent::class, function ($event): bool {
            return $event->triggerType === 'event';
        });
    });

    it('prevents duplicate manual allocations for same campaign', function (): void {
        // Create existing manual allocation
        $existingTransaction = WalletTransaction::factory()->create([
            'user_id'     => $this->customer->id,
            'type'        => TransactionTypeEnum::GIFT,
            'source_type' => TransactionSourceEnum::CAMPAIGN,
            'source_id'   => $this->campaign->id,
            'metadata'    => ['trigger_type' => 'manual'],
        ]);

        $data = new TriggerCampaignAllocationData(
            trigger_type: 'manual',
            trigger_event: null,
            reason: 'Another manual allocation'
        );

        $this->mockRecordAction->shouldNotReceive('execute');

        $result = $this->action->handle($data, $this->customer, $this->campaign);

        expect($result->id)->toBe($existingTransaction->id);
        expect($this->campaign->fresh()->total_usage_count)->toBe(0); // Should not increment
    });

    it('prevents duplicate event-based allocations for same trigger event', function (): void {
        // Create existing event allocation
        $existingTransaction = WalletTransaction::factory()->create([
            'user_id'     => $this->customer->id,
            'type'        => TransactionTypeEnum::GIFT,
            'source_type' => TransactionSourceEnum::CAMPAIGN,
            'source_id'   => $this->campaign->id,
            'metadata'    => ['trigger_event' => 'user_registration'],
        ]);

        $data = new TriggerCampaignAllocationData(
            trigger_type: 'event',
            trigger_event: 'user_registration'
        );

        $this->mockRecordAction->shouldNotReceive('execute');

        $result = $this->action->handle($data, $this->customer, $this->campaign);

        expect($result->id)->toBe($existingTransaction->id);
        expect($this->campaign->fresh()->total_usage_count)->toBe(0); // Should not increment
    });

    it('allows different event-based allocations for different trigger events', function (): void {
        // Create existing event allocation for registration
        WalletTransaction::factory()->create([
            'user_id'     => $this->customer->id,
            'type'        => TransactionTypeEnum::GIFT,
            'source_type' => TransactionSourceEnum::CAMPAIGN,
            'source_id'   => $this->campaign->id,
            'metadata'    => ['trigger_event' => 'user_registration'],
        ]);

        // Try to allocate for different event
        $data = new TriggerCampaignAllocationData(
            trigger_type: 'event',
            trigger_event: 'first_purchase'
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

    it('throws exception when user does not have wallet', function (): void {
        $userWithoutWallet = User::factory()->create();
        $userWithoutWallet->wallet->delete();
        $userWithoutWallet->refresh();
        $data = new TriggerCampaignAllocationData(
            trigger_type: 'manual',
            trigger_event: null,
        );

        expect(fn () => $this->action->handle($data, $userWithoutWallet, $this->campaign))
            ->toThrow(CustomValidationException::class);
    });

    it('throws exception when campaign is inactive', function (): void {
        $this->campaign->update(['is_active' => false]);

        $data = new TriggerCampaignAllocationData(
            trigger_type: 'manual',
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
            trigger_type: 'manual',
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
            trigger_type: 'manual',
            trigger_event: null,
        );

        expect(fn () => $this->action->handle($data, $this->customer, $this->campaign))
            ->toThrow(CustomValidationException::class);
    });

    it('throws exception when user has reached their usage limit', function (): void {
        $this->campaign->update(['usage_limit_per_user' => 1]);

        // Create existing transaction for user
        WalletTransaction::factory()->create([
            'user_id'     => $this->customer->id,
            'source_type' => TransactionSourceEnum::CAMPAIGN,
            'source_id'   => $this->campaign->id,
        ]);

        $data = new TriggerCampaignAllocationData(
            trigger_type: 'manual',
            trigger_event: null,
        );

        expect(fn () => $this->action->handle($data, $this->customer, $this->campaign))
            ->toThrow(CustomValidationException::class);
    });

    it('reuses existing transaction when writer returns idempotent existing row', function (): void {
        $data = new TriggerCampaignAllocationData(
            trigger_type: 'manual',
            trigger_event: null,
            reason: 'Manual allocation retry'
        );

        $existingTransaction = WalletTransaction::factory()->create([
            'user_id'         => $this->customer->id,
            'type'            => TransactionTypeEnum::GIFT,
            'amount'          => $this->campaign->amount,
            'source_type'     => TransactionSourceEnum::CAMPAIGN,
            'source_id'       => $this->campaign->id,
            'idempotency_key' => sprintf(
                'wallet-campaign:%d:user:%d:trigger:manual:event:manual',
                $this->campaign->id,
                $this->customer->id
            ),
        ]);

        $this->mockRecordAction
            ->shouldNotReceive('execute');

        $first  = $this->action->handle($data, $this->customer, $this->campaign);
        $second = $this->action->handle($data, $this->customer, $this->campaign);

        expect($first->id)->toBe($existingTransaction->id)
            ->and($second->id)->toBe($existingTransaction->id);
    });
});
