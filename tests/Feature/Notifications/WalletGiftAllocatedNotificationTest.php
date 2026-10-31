<?php

declare(strict_types=1);

use App\Actions\Admin\WalletCampaign\TriggerCampaignAllocationAction;
use App\Data\Admin\WalletCampaign\TriggerCampaignAllocationData;
use App\Enums\WalletCampaign\CampaignTriggerTypeEnum;
use App\Enums\WalletCampaign\CampaignTypeEnum;
use App\Models\Staff;
use App\Models\User;
use App\Models\WalletCampaign;
use App\Notifications\Wallet\WalletGiftAllocatedNotification;

mutates(WalletGiftAllocatedNotification::class);

/**
 * An active manual-allocation campaign a customer can receive a gift from.
 */
function giftCampaign(): WalletCampaign
{
    return WalletCampaign::factory()->create([
        'name'                 => 'Nowruz Gift',
        'type'                 => CampaignTypeEnum::MANUAL_ALLOCATION,
        'amount'               => 50_000,
        'is_active'            => true,
        'usage_limit_total'    => 1000,
        'usage_limit_per_user' => 2,
        'total_usage_count'    => 0,
        'starts_at'            => now()->subDay(),
        'ends_at'              => now()->addMonth(),
        'created_by'           => Staff::factory()->create()->id,
    ]);
}

it('delivers through the database channel only', function (): void {
    $notification = new WalletGiftAllocatedNotification(
        App\Models\WalletTransaction::factory()->make(['amount' => 50_000]),
        giftCampaign(),
    );

    expect($notification->via(new User()))->toBe(['database']);
});

it('builds the database payload from the campaign and the transaction', function (): void {
    $campaign    = giftCampaign();
    $transaction = App\Models\WalletTransaction::factory()->make(['id' => 77, 'amount' => 50_000]);

    $payload = (new WalletGiftAllocatedNotification($transaction, $campaign))->toDatabase(new User());

    expect($payload['type'])->toBe('wallet_gift_allocated')
        ->and($payload['title'])->toBe(__('messages.notifications.wallet_gift_allocated.title'))
        ->and($payload['message'])->toContain('50,000')
        ->and($payload['message'])->toContain($campaign->name)
        ->and($payload['resource_type'])->toBe('wallet_transaction')
        ->and($payload['resource_id'])->toBe(77);
});

it('notifies the customer exactly once per allocated gift', function (): void {
    $campaign = giftCampaign();
    $customer = User::factory()->create();
    $action   = app(TriggerCampaignAllocationAction::class);

    $data = new TriggerCampaignAllocationData(
        trigger_type: CampaignTriggerTypeEnum::MANUAL,
        trigger_event: null,
        reason: 'Admin manual allocation',
        idempotency_key: 'gift-request-1',
    );

    $action->handle($data, $customer, $campaign);

    expect($customer->notifications()->count())->toBe(1);
    expect($customer->notifications()->first()->data['resource_type'])->toBe('wallet_transaction');

    // A replay returns the original ledger row before the event is dispatched.
    $action->handle($data, $customer, $campaign);

    expect($customer->notifications()->count())->toBe(1);
});
