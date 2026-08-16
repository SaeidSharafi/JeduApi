<?php

declare(strict_types=1);

use App\Actions\Admin\WalletCampaign\TriggerCampaignAllocationAction;
use App\Data\Admin\WalletCampaign\TriggerCampaignAllocationData;
use App\Enums\Wallet\TransactionSourceEnum;
use App\Enums\WalletCampaign\CampaignTypeEnum;
use App\Models\Staff;
use App\Models\User;
use App\Models\WalletCampaign;
use App\Models\WalletTransaction;

uses(Tests\Support\Traits\AuthTestTrait::class);

beforeEach(function (): void {
    $this->customer = User::factory()->create();
    $this->staff    = Staff::factory()->create();

    $this->action = app(TriggerCampaignAllocationAction::class);

    $this->campaign = WalletCampaign::factory()->create([
        'type'                 => CampaignTypeEnum::REGISTRATION_BONUS,
        'amount'               => 50000,
        'is_active'            => true,
        'usage_limit_total'    => 100,
        'usage_limit_per_user' => 1,
        'total_usage_count'    => 0,
        'starts_at'            => now()->subDay(),
        'ends_at'              => null,
        'threshold_scope'      => 'lifetime',
        'metadata'             => null,
        'created_by'           => $this->staff->id,
    ]);

    $this->data = new TriggerCampaignAllocationData(
        trigger_type: 'event',
        trigger_event: 'profile_completed',
        metadata: [],
    );
});

describe('TriggerCampaignAllocationAction expiry deadline', function (): void {
    it('sets expires_at from metadata.expiry_days (relative, days from receipt)', function (): void {
        $this->campaign->update(['metadata' => ['expiry_days' => 10]]);

        $this->action->handle($this->data, $this->customer, $this->campaign);

        $transaction = WalletTransaction::query()
            ->where('source_type', TransactionSourceEnum::CAMPAIGN)
            ->where('source_id', $this->campaign->id)
            ->firstOrFail();

        $expected = now()->addDays(10);
        expect($transaction->expires_at)->not->toBeNull();
        expect($transaction->expires_at->format('Y-m-d'))->toBe($expected->format('Y-m-d'));
    });

    it('sets expires_at from campaign ends_at (absolute) when no relative config', function (): void {
        $endsAt = now()->addMonth();
        $this->campaign->update(['ends_at' => $endsAt]);

        $this->action->handle($this->data, $this->customer, $this->campaign);

        $transaction = WalletTransaction::query()
            ->where('source_type', TransactionSourceEnum::CAMPAIGN)
            ->where('source_id', $this->campaign->id)
            ->firstOrFail();

        expect($transaction->expires_at?->format('Y-m-d H:i:s'))->toBe($endsAt->format('Y-m-d H:i:s'));
    });

    it('leaves expires_at null when the campaign configures no expiry', function (): void {
        $this->action->handle($this->data, $this->customer, $this->campaign);

        $transaction = WalletTransaction::query()
            ->where('source_type', TransactionSourceEnum::CAMPAIGN)
            ->where('source_id', $this->campaign->id)
            ->firstOrFail();

        expect($transaction->expires_at)->toBeNull();
    });

    it('prefers relative expiry_days over absolute ends_at', function (): void {
        $this->campaign->update([
            'ends_at'  => now()->addMonth(),
            'metadata' => ['expiry_days' => 5],
        ]);

        $this->action->handle($this->data, $this->customer, $this->campaign);

        $transaction = WalletTransaction::query()
            ->where('source_type', TransactionSourceEnum::CAMPAIGN)
            ->where('source_id', $this->campaign->id)
            ->firstOrFail();

        $expected = now()->addDays(5);
        expect($transaction->expires_at?->format('Y-m-d'))->toBe($expected->format('Y-m-d'));
    });
});
