<?php

declare(strict_types=1);

use App\Actions\Admin\WalletCampaign\EvaluateThresholdRewardAction;
use App\Enums\Payment\PaymentPurposeEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Enums\Wallet\TransactionSourceEnum;
use App\Enums\Wallet\TransactionTypeEnum;
use App\Enums\WalletCampaign\CampaignTypeEnum;
use App\Enums\WalletCampaign\ThresholdScopeEnum;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Models\WalletCampaign;
use App\Models\WalletTransaction;
use Carbon\CarbonInterface;
use Tests\Support\Traits\AuthTestTrait;

uses(AuthTestTrait::class);

/**
 * Creates a paid order for the user: an ORDER-purpose COMPLETED payment on an
 * order owned by the user, paid at the given time.
 */
function paidOrder(User $user, int $amount, ?CarbonInterface $paidAt = null): Order
{
    $order = Order::factory()->create([
        'customer_id' => $user->id,
        'grand_total' => $amount,
        'created_at'  => $paidAt ?? now(),
    ]);

    Payment::factory()->create([
        'order_id'    => $order->id,
        'customer_id' => $user->id,
        'amount'      => $amount,
        'purpose'     => PaymentPurposeEnum::ORDER,
        'status'      => PaymentStatusEnum::COMPLETED,
        'created_at'  => $paidAt ?? now(),
    ]);

    return $order;
}

function thresholdGiftRows(User $user, WalletCampaign $campaign): int
{
    return WalletTransaction::query()
        ->where('user_id', $user->id)
        ->where('source_type', TransactionSourceEnum::CAMPAIGN)
        ->where('source_id', $campaign->id)
        ->where('type', TransactionTypeEnum::GIFT)
        ->count();
}

beforeEach(function (): void {
    $this->user   = User::factory()->create();
    $this->action = app(EvaluateThresholdRewardAction::class);
});

describe('EvaluateThresholdRewardAction', function (): void {
    it('does not allocate a loyalty reward when the spend threshold is not crossed', function (): void {
        $campaign = WalletCampaign::factory()->loyaltyReward(thresholdAmount: 100000)->lifetime()->create([
            'is_active' => true,
            'starts_at' => null,
            'ends_at'   => null,
        ]);

        paidOrder($this->user, 40000);
        paidOrder($this->user, 50000); // 90_000 total < 100_000 threshold

        $result = $this->action->handle($this->user, $campaign);

        expect($result)->toBeNull()
            ->and(thresholdGiftRows($this->user, $campaign))->toBe(0);
    });

    it('allocates a loyalty reward exactly once when the spend threshold is crossed', function (): void {
        $campaign = WalletCampaign::factory()->loyaltyReward(thresholdAmount: 100000)->lifetime()->create([
            'is_active' => true,
            'starts_at' => null,
            'ends_at'   => null,
        ]);

        paidOrder($this->user, 60000);
        paidOrder($this->user, 50000); // crosses to 110_000

        $this->action->handle($this->user, $campaign);

        expect(thresholdGiftRows($this->user, $campaign))->toBe(1);
        expect($this->user->wallet->fresh()->gift_balance)->toBe($campaign->amount);

        // Further paid orders must not refire the reward — the per-user limit
        // (reached after the first allocation) rejects a second allocation.
        paidOrder($this->user, 50000);
        expect(fn () => $this->action->handle($this->user, $campaign))
            ->toThrow(App\Exceptions\CustomValidationException::class);

        expect(thresholdGiftRows($this->user, $campaign))->toBe(1);
    });

    it('does not allocate a milestone reward when the order-count threshold is not crossed', function (): void {
        $campaign = WalletCampaign::factory()->milestoneReward(thresholdOrderCount: 3)->lifetime()->create([
            'is_active' => true,
            'starts_at' => null,
            'ends_at'   => null,
        ]);

        paidOrder($this->user, 10000);
        paidOrder($this->user, 10000); // 2 paid orders < 3 threshold

        $result = $this->action->handle($this->user, $campaign);

        expect($result)->toBeNull()
            ->and(thresholdGiftRows($this->user, $campaign))->toBe(0);
    });

    it('allocates a milestone reward exactly once when the order-count threshold is crossed', function (): void {
        $campaign = WalletCampaign::factory()->milestoneReward(thresholdOrderCount: 3)->lifetime()->create([
            'is_active' => true,
            'starts_at' => null,
            'ends_at'   => null,
        ]);

        paidOrder($this->user, 10000);
        paidOrder($this->user, 10000);
        paidOrder($this->user, 10000); // 3rd paid order crosses

        $this->action->handle($this->user, $campaign);

        expect(thresholdGiftRows($this->user, $campaign))->toBe(1);
        expect($this->user->wallet->fresh()->gift_balance)->toBe($campaign->amount);

        paidOrder($this->user, 10000); // 4th order must not refire
        expect(fn () => $this->action->handle($this->user, $campaign))
            ->toThrow(App\Exceptions\CustomValidationException::class);

        expect(thresholdGiftRows($this->user, $campaign))->toBe(1);
    });

    it('measures lifetime scope across all history', function (): void {
        $campaign = WalletCampaign::factory()->loyaltyReward(thresholdAmount: 100000)->lifetime()->create([
            'is_active' => true,
            'starts_at' => null,
            'ends_at'   => null,
        ]);

        // Paid before any campaign window existed — still counts for lifetime.
        paidOrder($this->user, 120000, now()->subMonths(6));

        $result = $this->action->handle($this->user, $campaign);

        expect($result)->not->toBeNull()
            ->and(thresholdGiftRows($this->user, $campaign))->toBe(1);
    });

    it('measures windowed scope only within the campaign date window', function (): void {
        $campaign = WalletCampaign::factory()->loyaltyReward(thresholdAmount: 100000)->create([
            'type'                 => CampaignTypeEnum::LOYALTY_REWARD->value,
            'is_active'            => true,
            'starts_at'            => now()->subWeek(),
            'ends_at'              => now()->addWeek(),
            'threshold_scope'      => ThresholdScopeEnum::WINDOWED->value,
            'usage_limit_per_user' => 1,
            'metadata'             => ['threshold_amount' => 100000],
        ]);

        paidOrder($this->user, 90000, now()->subMonths(2)); // outside window — not counted
        paidOrder($this->user, 60000, now()->subDay()); // inside window
        paidOrder($this->user, 50000, now()->subDay()); // inside window — crosses to 110_000

        $result = $this->action->handle($this->user, $campaign);

        expect($result)->not->toBeNull()
            ->and(thresholdGiftRows($this->user, $campaign))->toBe(1);
    });

    it('does not allocate when the windowed total stays below threshold', function (): void {
        $campaign = WalletCampaign::factory()->loyaltyReward(thresholdAmount: 100000)->create([
            'type'                 => CampaignTypeEnum::LOYALTY_REWARD->value,
            'is_active'            => true,
            'starts_at'            => now()->subWeek(),
            'ends_at'              => now()->addWeek(),
            'threshold_scope'      => ThresholdScopeEnum::WINDOWED->value,
            'usage_limit_per_user' => 1,
            'metadata'             => ['threshold_amount' => 100000],
        ]);

        paidOrder($this->user, 90000, now()->subMonths(2)); // outside window
        paidOrder($this->user, 5000, now()->subDay()); // inside window — only 5_000

        $result = $this->action->handle($this->user, $campaign);

        expect($result)->toBeNull()
            ->and(thresholdGiftRows($this->user, $campaign))->toBe(0);
    });

    it('honors the per-user limit when the threshold is crossed', function (): void {
        $campaign = WalletCampaign::factory()->loyaltyReward(thresholdAmount: 100000)->lifetime()->create([
            'is_active'            => true,
            'starts_at'            => null,
            'ends_at'              => null,
            'usage_limit_per_user' => 0, // already exhausted
        ]);

        paidOrder($this->user, 120000);

        expect(fn () => $this->action->handle($this->user, $campaign))
            ->toThrow(App\Exceptions\CustomValidationException::class);

        expect(thresholdGiftRows($this->user, $campaign))->toBe(0);
    });

    it('ignores wallet top-up payments when measuring', function (): void {
        $campaign = WalletCampaign::factory()->loyaltyReward(thresholdAmount: 100000)->lifetime()->create([
            'is_active' => true,
            'starts_at' => null,
            'ends_at'   => null,
        ]);

        Payment::factory()->create([
            'customer_id' => $this->user->id,
            'order_id'    => null,
            'amount'      => 200000,
            'purpose'     => PaymentPurposeEnum::WALLET_TOPUP,
            'status'      => PaymentStatusEnum::COMPLETED,
        ]);

        $result = $this->action->handle($this->user, $campaign);

        expect($result)->toBeNull()
            ->and(thresholdGiftRows($this->user, $campaign))->toBe(0);
    });

    it('ignores incomplete (non-completed) payments when measuring', function (): void {
        $campaign = WalletCampaign::factory()->loyaltyReward(thresholdAmount: 100000)->lifetime()->create([
            'is_active' => true,
            'starts_at' => null,
            'ends_at'   => null,
        ]);

        $order = Order::factory()->create(['customer_id' => $this->user->id, 'grand_total' => 150000]);
        Payment::factory()->create([
            'order_id'    => $order->id,
            'customer_id' => $this->user->id,
            'amount'      => 150000,
            'purpose'     => PaymentPurposeEnum::ORDER,
            'status'      => PaymentStatusEnum::PENDING,
        ]);

        $result = $this->action->handle($this->user, $campaign);

        expect($result)->toBeNull()
            ->and(thresholdGiftRows($this->user, $campaign))->toBe(0);
    });

    it('does not fail when the user has no wallet', function (): void {
        $campaign = WalletCampaign::factory()->loyaltyReward(thresholdAmount: 100000)->lifetime()->create([
            'is_active' => true,
            'starts_at' => null,
            'ends_at'   => null,
        ]);

        paidOrder($this->user, 120000);
        $this->user->wallet->delete();
        $this->user->refresh();

        expect(fn () => $this->action->handle($this->user, $campaign))
            ->toThrow(App\Exceptions\CustomValidationException::class);
    });
});
