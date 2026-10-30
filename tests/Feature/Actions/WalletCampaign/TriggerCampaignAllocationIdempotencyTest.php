<?php

declare(strict_types=1);

use App\Actions\Admin\WalletCampaign\TriggerCampaignAllocationAction;
use App\Data\Admin\WalletCampaign\TriggerCampaignAllocationData;
use App\Enums\Wallet\TransactionSourceEnum;
use App\Enums\Wallet\TransactionTypeEnum;
use App\Enums\WalletCampaign\CampaignTriggerEventEnum;
use App\Enums\WalletCampaign\CampaignTriggerTypeEnum;
use App\Enums\WalletCampaign\CampaignTypeEnum;
use App\Models\Staff;
use App\Models\User;
use App\Models\WalletCampaign;
use App\Models\WalletTransaction;

function campaignOfType(CampaignTypeEnum $type): WalletCampaign
{
    $staff = Staff::factory()->create();

    return WalletCampaign::factory()->create([
        'type'                 => $type,
        'amount'               => 50000,
        'is_active'            => true,
        'usage_limit_total'    => 100,
        'usage_limit_per_user' => 10,
        'total_usage_count'    => 0,
        'starts_at'            => now()->subDay(),
        'ends_at'              => now()->addDay(),
        'created_by'           => $staff->id,
    ]);
}

function giftRows(User $customer, WalletCampaign $campaign): int
{
    return WalletTransaction::query()
        ->where('user_id', $customer->id)
        ->where('source_type', TransactionSourceEnum::CAMPAIGN)
        ->where('source_id', $campaign->id)
        ->where('type', TransactionTypeEnum::GIFT)
        ->count();
}

it('returns the same transaction when a manual grant is replayed with the same request key', function (): void {
    $customer = User::factory()->create();
    $campaign = campaignOfType(CampaignTypeEnum::MANUAL_ALLOCATION);

    $data = new TriggerCampaignAllocationData(
        trigger_type: CampaignTriggerTypeEnum::MANUAL,
        trigger_event: null,
        reason: 'Retry-safe grant',
        metadata: [],
        idempotency_key: 'submit-uuid',
    );

    $action = app(TriggerCampaignAllocationAction::class);

    $first  = $action->handle($data, $customer, $campaign);
    $second = $action->handle($data, $customer, $campaign);

    expect($second->id)->toBe($first->id)
        ->and(giftRows($customer, $campaign))->toBe(1)
        ->and($campaign->fresh()->total_usage_count)->toBe(1);
});

it('creates separate transactions for repeated manual grants without a key', function (): void {
    $customer = User::factory()->create();
    $campaign = campaignOfType(CampaignTypeEnum::MANUAL_ALLOCATION);

    $data = new TriggerCampaignAllocationData(
        trigger_type: CampaignTriggerTypeEnum::MANUAL,
        trigger_event: null,
        reason: 'Repeat grant',
        metadata: [],
    );

    $action = app(TriggerCampaignAllocationAction::class);

    $first  = $action->handle($data, $customer, $campaign);
    $second = $action->handle($data, $customer, $campaign);

    expect($second->id)->not->toBe($first->id)
        ->and(giftRows($customer, $campaign))->toBe(2)
        ->and($campaign->fresh()->total_usage_count)->toBe(2);
});

it('returns the same transaction for a replayed event allocation', function (): void {
    $customer = User::factory()->create();
    $campaign = campaignOfType(CampaignTypeEnum::REGISTRATION_BONUS);

    $data = new TriggerCampaignAllocationData(
        trigger_type: CampaignTriggerTypeEnum::EVENT,
        trigger_event: CampaignTriggerEventEnum::PROFILE_COMPLETED,
        metadata: [],
    );

    $action = app(TriggerCampaignAllocationAction::class);

    $first  = $action->handle($data, $customer, $campaign);
    $second = $action->handle($data, $customer, $campaign);

    expect($second->id)->toBe($first->id)
        ->and(giftRows($customer, $campaign))->toBe(1)
        ->and($campaign->fresh()->total_usage_count)->toBe(1);
});
