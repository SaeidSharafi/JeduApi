<?php

declare(strict_types=1);

use App\Enums\Order\OrderStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Backfills reservation rows for orders that settled before
 * `discount_promotion_usages` existed.
 *
 * Those completions incremented `discount_promotions.total_usage_count` /
 * `discount_coupons.usage_count` but left no usage row. Caps are now counted
 * from usage rows, so without this backfill every historical completion would
 * silently restore the capacity it consumed at deployment.
 *
 * Only settled orders are read. Cancelled/failed orders never consumed
 * capacity and pending/processing orders are holds, already covered by the
 * reservation expiry window (#156) — creating rows for either would invent
 * reservations that the original order never had.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        /** @var array<int, array<string, int>> $couponIdsByPromotion promotion id => coupon code => coupon id */
        $couponIdsByPromotion = [];
        foreach (DB::table('discount_coupons')->select(['id', 'discount_promotion_id', 'code'])->get() as $coupon) {
            $couponIdsByPromotion[$coupon->discount_promotion_id][$coupon->code] = (int) $coupon->id;
        }

        // A promotion may have been deleted since the order settled; the usage
        // table cascades on promotion deletion, so such orders are skipped.
        /** @var array<int, int> $existingPromotionIds promotion id => promotion id */
        $existingPromotionIds = array_flip(DB::table('discount_promotions')->pluck('id')->all());

        DB::table('orders')
            ->whereIn('status', [
                OrderStatusEnum::COMPLETED->value,
                OrderStatusEnum::REFUNDED->value,
                OrderStatusEnum::PARTIALLY_REFUNDED->value,
            ])
            ->whereNotNull('applied_cart_discounts_json')
            ->select(['id', 'customer_id', 'applied_coupon_code', 'applied_cart_discounts_json', 'created_at'])
            ->orderBy('id')
            ->chunkById(500, function (Collection $orders) use ($couponIdsByPromotion, $existingPromotionIds): void {
                $rows = [];

                /** @var object{id: int, customer_id: int, applied_coupon_code: ?string, applied_cart_discounts_json: string, created_at: ?string} $order */
                foreach ($orders as $order) {
                    $discounts = json_decode($order->applied_cart_discounts_json, true);
                    if (! is_array($discounts) || $discounts === []) {
                        continue;
                    }

                    $timestamp = $order->created_at ?? now();

                    foreach ($discounts as $discount) {
                        $promotionId = $discount['promotion_id'] ?? null;
                        if (! $promotionId || ! isset($existingPromotionIds[$promotionId])) {
                            continue;
                        }

                        // Resolve the coupon that triggered this promotion's
                        // discount, preferring the code recorded on the
                        // discount itself and falling back to the order-level
                        // code. Both come from the preloaded map, so the loop
                        // issues no queries.
                        $couponId = $couponIdsByPromotion[$promotionId][$discount['coupon_code'] ?? null]
                            ?? $couponIdsByPromotion[$promotionId][$order->applied_coupon_code]
                            ?? null;

                        $rows[] = [
                            'discount_promotion_id' => $promotionId,
                            'discount_coupon_id'    => $couponId,
                            'customer_id'           => $order->customer_id,
                            'order_id'              => $order->id,
                            'created_at'            => $timestamp,
                            'updated_at'            => $timestamp,
                        ];
                    }
                }

                if ($rows !== []) {
                    // unique(discount_promotion_id, order_id) makes re-runs no-ops.
                    DB::table('discount_promotion_usages')->insertOrIgnore($rows);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Deliberately empty: a backfilled row is indistinguishable from one
        // written at order placement, so deleting it on rollback would silently
        // restore capacity that the order already consumed.
    }
};
