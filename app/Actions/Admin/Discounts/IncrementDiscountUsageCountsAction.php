<?php

declare(strict_types=1);

namespace App\Actions\Admin\Discounts;

use App\Models\DiscountPromotion;
use App\Models\Order;

final readonly class IncrementDiscountUsageCountsAction
{
    /**
     * Increment coupon/promotion usage counters for a completed order.
     *
     * Reads the discount audit trail persisted at order creation
     * (orders.applied_cart_discounts_json) and the coupon code that triggered
     * the discounts (orders.applied_coupon_code), so no recalculation happens
     * at completion time. Idempotency is the caller's responsibility — invoke
     * only when an order transitions into COMPLETED.
     */
    public function handle(Order $order): void
    {
        $couponCode = $order->applied_coupon_code;
        $discounts  = $order->applied_cart_discounts_json ?? [];

        foreach ($discounts as $discount) {
            $promotionId = $discount['promotion_id'] ?? null;
            if (! $promotionId) {
                continue;
            }

            $promotion = DiscountPromotion::find($promotionId);
            if (! $promotion) {
                continue;
            }

            $promotion->increment('total_usage_count');

            // Coupon usage only incremented on the promotion that owns this coupon.
            // Safe: coupons() query on a promotion without this code affects 0 rows.
            if ($couponCode) {
                $promotion->coupons()
                    ->where('code', $couponCode)
                    ->increment('usage_count');
            }
        }
    }
}
