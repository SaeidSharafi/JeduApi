<?php

declare(strict_types=1);

use App\Actions\Admin\WalletCampaign\TriggerCampaignAllocationAction;
use App\Data\Admin\WalletCampaign\TriggerCampaignAllocationData;
use App\Enums\Wallet\TransactionSourceEnum;
use App\Enums\Wallet\TransactionTypeEnum;
use App\Enums\WalletCampaign\CampaignTypeEnum;
use App\Models\Staff;
use App\Models\User;
use App\Models\WalletCampaign;
use App\Models\WalletTransaction;

it('returns same transaction for duplicate manual campaign trigger', function (): void {
    $customer = User::factory()->create();
    $staff    = Staff::factory()->create();

    $campaign = WalletCampaign::factory()->create([
        'type'                 => CampaignTypeEnum::MANUAL_ALLOCATION,
        'amount'               => 50000,
        'is_active'            => true,
        'usage_limit_total'    => 100,
        'usage_limit_per_user' => 10,
        'total_usage_count'    => 0,
        'starts_at'            => now()->subDay(),
        'ends_at'              => now()->addDay(),
        'created_by'           => $staff->id,
    ]);

    $action = app(TriggerCampaignAllocationAction::class);
    $data   = new TriggerCampaignAllocationData(
        trigger_type: 'manual',
        trigger_event: null,
        reason: 'Manual grant',
        metadata: []
    );

    $first  = $action->handle($data, $customer, $campaign);
    $second = $action->handle($data, $customer, $campaign);

    expect($second->id)->toBe($first->id);

    $rows = WalletTransaction::query()
        ->where('user_id', $customer->id)
        ->where('source_type', TransactionSourceEnum::CAMPAIGN)
        ->where('source_id', $campaign->id)
        ->where('type', TransactionTypeEnum::GIFT)
        ->count();

    expect($rows)->toBe(1);
});
