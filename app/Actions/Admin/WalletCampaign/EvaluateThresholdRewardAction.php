<?php

declare(strict_types=1);

namespace App\Actions\Admin\WalletCampaign;

use App\Data\Admin\WalletCampaign\TriggerCampaignAllocationData;
use App\Enums\Payment\PaymentPurposeEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Enums\WalletCampaign\CampaignTypeEnum;
use App\Enums\WalletCampaign\ThresholdScopeEnum;
use App\Models\Payment;
use App\Models\User;
use App\Models\WalletCampaign;
use App\Models\WalletTransaction;
use Carbon\CarbonInterface;

/**
 * Evaluates a payment-completed event against loyalty_reward and
 * milestone_reward campaigns. loyalty_reward measures the user's cumulative
 * paid order total; milestone_reward measures the user's paid order count.
 * Measurement respects the campaign's threshold_scope: lifetime measures all
 * history, windowed measures only orders within the campaign's date window.
 *
 * A gift is allocated exactly once, when the user's measured value first
 * crosses the campaign's threshold. Refire protection comes from the shared
 * allocation action (duplicate trigger-event check + deterministic idempotency
 * key) plus the campaign's per-user limit.
 */
final readonly class EvaluateThresholdRewardAction
{
    public function __construct(
        private TriggerCampaignAllocationAction $allocationAction
    ) {}

    /**
     * Allocate a gift when the user has crossed the campaign's threshold.
     *
     * @return WalletTransaction|null the allocation (or existing idempotent row) when the
     *                                threshold is crossed, null when it is not
     */
    public function handle(User $user, WalletCampaign $campaign): ?WalletTransaction
    {
        $threshold = $this->resolveThreshold($campaign);

        if ($threshold === null) {
            // No threshold configured for this campaign type — nothing to evaluate.
            return null;
        }

        if ($this->measure($user, $campaign) < $threshold) {
            return null;
        }

        return $this->allocationAction->handle(
            data: new TriggerCampaignAllocationData(
                trigger_type: 'event',
                trigger_event: 'payment_completed',
                metadata: ['payment_completed_at' => now()->toISOString()],
            ),
            user: $user,
            campaign: $campaign,
        );
    }

    /**
     * Resolve the campaign's reward threshold from its type-specific metadata key.
     */
    private function resolveThreshold(WalletCampaign $campaign): ?int
    {
        $key = match ($campaign->type) {
            CampaignTypeEnum::LOYALTY_REWARD   => 'threshold_amount',
            CampaignTypeEnum::MILESTONE_REWARD => 'threshold_order_count',
            default                            => null,
        };

        if ($key === null) {
            return null;
        }

        $threshold = $campaign->metadata[$key] ?? null;

        return is_numeric($threshold) ? (int) $threshold : null;
    }

    /**
     * Measure the user's current value for the campaign's metric, honoring
     * threshold_scope. loyalty_reward sums paid order amounts; milestone_reward
     * counts paid orders. A paid order is one with a completed ORDER-purpose
     * payment.
     */
    private function measure(User $user, WalletCampaign $campaign): int
    {
        return match ($campaign->type) {
            CampaignTypeEnum::LOYALTY_REWARD   => $this->measurePaidOrderTotal($user, $campaign),
            CampaignTypeEnum::MILESTONE_REWARD => $this->measurePaidOrderCount($user, $campaign),
            default                            => 0,
        };
    }

    private function measurePaidOrderTotal(User $user, WalletCampaign $campaign): int
    {
        return (int) $this->paidPaymentsQuery($user, $campaign)->sum('amount');
    }

    private function measurePaidOrderCount(User $user, WalletCampaign $campaign): int
    {
        return $this->paidPaymentsQuery($user, $campaign)->distinct('order_id')->count('order_id');
    }

    /**
     * Base query of completed ORDER-purpose payments for the user, optionally
     * bounded by the campaign's window for windowed scope.
     *
     * @return \Illuminate\Database\Eloquent\Builder<Payment>
     */
    private function paidPaymentsQuery(User $user, WalletCampaign $campaign): \Illuminate\Database\Eloquent\Builder
    {
        $query = Payment::query()
            ->where('customer_id', $user->id)
            ->where('purpose', PaymentPurposeEnum::ORDER)
            ->where('status', PaymentStatusEnum::COMPLETED);

        if ($campaign->threshold_scope === ThresholdScopeEnum::WINDOWED) {
            $query->whereBetween('created_at', [
                $this->windowStart($campaign),
                $this->windowEnd($campaign),
            ]);
        }

        return $query;
    }

    private function windowStart(WalletCampaign $campaign): CarbonInterface
    {
        return $campaign->starts_at ?? now()->startOfDay();
    }

    private function windowEnd(WalletCampaign $campaign): CarbonInterface
    {
        return $campaign->ends_at ?? now()->endOfDay();
    }
}
