<?php

declare(strict_types=1);

namespace App\Actions\Admin\Discounts;

use App\Models\DiscountPromotion;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

final readonly class IncrementDiscountUsageCountsAction
{
    /**
     * Increment coupon/promotion usage counters for a completed order.
     *
     * Reads the journeys persisted at order creation — the discount audit trail
     * (orders.applied_cart_discounts_json), the rewards the order owes
     * (orders.reward_intents_json) and the coupon code that triggered them
     * (orders.applied_coupon_code) — so no recalculation happens at completion
     * time. A promotion that only granted a reward counts too, otherwise its
     * caps would never be consumed. Idempotency is guaranteed internally: the
     * order row is locked for update inside a transaction, so the counter
     * increments and the orders.discount_usage_incremented_at flag are written
     * atomically and concurrent completion paths can never double-increment.
     */
    public function handle(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($lockedOrder->discount_usage_incremented_at !== null) {
                return;
            }

            $couponCode = $lockedOrder->applied_coupon_code;

            foreach ($lockedOrder->usedPromotionIds() as $promotionId) {
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

            $lockedOrder->forceFill(['discount_usage_incremented_at' => now()])->saveQuietly();
        });
    }
}
