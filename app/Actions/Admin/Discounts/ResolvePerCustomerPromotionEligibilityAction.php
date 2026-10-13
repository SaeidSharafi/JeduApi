<?php

declare(strict_types=1);

namespace App\Actions\Admin\Discounts;

use App\Enums\Order\OrderStatusEnum;
use App\Models\DiscountPromotion;
use App\Models\DiscountPromotionUsage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Single owner of the per-customer promotion allowance rule.
 *
 * A customer's active usage of a Promotion is the count of their usage rows
 * whose order is not cancelled/failed (pending/processing reserve it,
 * completed/refunded consume it). Exhaustion makes a promotion ineligible for
 * that customer — it must be filtered out of a calculation, never used to
 * block a sale.
 */
final readonly class ResolvePerCustomerPromotionEligibilityAction
{
    /**
     * Drop promotions whose per-customer allowance this customer has used up,
     * preserving the incoming (priority) order.
     *
     * @param  Collection<int, DiscountPromotion>  $promotions
     * @return Collection<int, DiscountPromotion>
     */
    public function filterExhausted(Collection $promotions, ?int $customerId): Collection
    {
        if ($customerId === null || $promotions->isEmpty()) {
            return $promotions;
        }

        $limitedPromotions = $promotions->whereNotNull('usage_limit_per_customer');

        if ($limitedPromotions->isEmpty()) {
            return $promotions;
        }

        $activeUsageCounts = DiscountPromotionUsage::query()
            ->active()
            ->where('customer_id', $customerId)
            ->whereIn('discount_promotion_id', $limitedPromotions->modelKeys())
            ->selectRaw('discount_promotion_id, count(*) as active_usage_count')
            ->groupBy('discount_promotion_id')
            ->pluck('active_usage_count', 'discount_promotion_id');

        return $promotions
            ->reject(fn (DiscountPromotion $promotion): bool => $this->isExhausted(
                $promotion,
                (int) ($activeUsageCounts[$promotion->id] ?? 0),
            ))
            ->values();
    }

    /**
     * Translated blocking message when the customer's allowance for this
     * promotion is used up, or null when the promotion is still available.
     *
     * Names the pending order that holds the slot when one exists.
     */
    public function blockingMessage(DiscountPromotion $promotion, int $customerId): ?string
    {
        if ($promotion->usage_limit_per_customer === null) {
            return null;
        }

        if (! $this->isExhausted($promotion, $this->activeUsageCount($promotion, $customerId))) {
            return null;
        }

        $blockingOrder = DiscountPromotionUsage::query()
            ->active()
            ->where('discount_promotion_id', $promotion->id)
            ->where('customer_id', $customerId)
            ->whereHas('order', fn (Builder $query): Builder => $query->where('status', OrderStatusEnum::PENDING->value))
            ->with('order')
            ->latest('id')
            ->first()
            ?->order;

        return $blockingOrder
            ? __('messages.checkout.coupon_usage_pending_order_blocked', ['order_id' => $blockingOrder->increment_id])
            : __('messages.checkout.coupon_usage_limit_reached');
    }

    private function activeUsageCount(DiscountPromotion $promotion, int $customerId): int
    {
        return DiscountPromotionUsage::query()
            ->active()
            ->where('discount_promotion_id', $promotion->id)
            ->where('customer_id', $customerId)
            ->count();
    }

    private function isExhausted(DiscountPromotion $promotion, int $activeUsageCount): bool
    {
        return $promotion->usage_limit_per_customer !== null
            && $activeUsageCount >= $promotion->usage_limit_per_customer;
    }
}
