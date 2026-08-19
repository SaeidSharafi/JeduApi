<?php

declare(strict_types=1);

namespace App\Actions\Admin\Discounts;

use App\Models\DiscountCoupon;
use App\Models\DiscountPromotionUsage;
use App\Models\Order;

/**
 * Records one usage slot per applied Promotion for an order.
 *
 * Called at order creation (shop checkout and admin order creation). The
 * row is the "pending" slot: it holds the per-customer allowance until the
 * order completes (consumed) or is cancelled/failed (released).
 */
final readonly class RecordPromotionUsageAction
{
    public function handle(Order $order): void
    {
        $discounts = $order->applied_cart_discounts_json ?? [];

        foreach ($discounts as $discount) {
            $promotionId = $discount['promotion_id'] ?? null;
            if (! $promotionId) {
                continue;
            }

            // Resolve the coupon that triggered this promotion's discount.
            // The limit pools across all coupon codes of a Promotion, so the
            // coupon id is stored for audit only — enforcement is per-promotion.
            $couponId   = null;
            $couponCode = $discount['coupon_code'] ?? null;
            if ($couponCode) {
                $couponId = DiscountCoupon::query()
                    ->where('discount_promotion_id', $promotionId)
                    ->where('code', $couponCode)
                    ->value('id');
            }

            DiscountPromotionUsage::firstOrCreate(
                [
                    'discount_promotion_id' => $promotionId,
                    'order_id'              => $order->id,
                ],
                [
                    'discount_coupon_id' => $couponId,
                    'customer_id'        => $order->customer_id,
                ]
            );
        }
    }
}
