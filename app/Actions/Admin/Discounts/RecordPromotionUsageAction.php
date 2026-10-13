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
 *
 * A promotion counts as applied when it marked a line down, when it only
 * granted a reward, or both — see {@see Order::usedPromotionIds()}. Reading
 * only the price-discount log would leave a reward-only promotion's caps and
 * per-customer allowance permanently unconsumed.
 */
final readonly class RecordPromotionUsageAction
{
    public function handle(Order $order): void
    {
        $couponCodeByPromotion = collect($order->applied_cart_discounts_json ?? [])
            ->filter(fn (array $discount): bool => ! empty($discount['promotion_id']))
            ->mapWithKeys(fn (array $discount): array => [
                (int) $discount['promotion_id'] => $discount['coupon_code'] ?? null,
            ]);

        foreach ($order->usedPromotionIds() as $promotionId) {
            // The entry's own code wins; a reward-only promotion has no entry,
            // so it falls back to the code the order consumed. The lookup is
            // scoped to the promotion, so a code owned by another promotion
            // resolves to null — the limit pools per promotion, and the coupon
            // id is stored for audit only.
            $couponCode = $couponCodeByPromotion[$promotionId] ?? $order->applied_coupon_code;

            $couponId = $couponCode
                ? DiscountCoupon::query()
                    ->where('discount_promotion_id', $promotionId)
                    ->where('code', $couponCode)
                    ->value('id')
                : null;

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
