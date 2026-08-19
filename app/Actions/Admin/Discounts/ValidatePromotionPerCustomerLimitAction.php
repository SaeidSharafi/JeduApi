<?php

declare(strict_types=1);

namespace App\Actions\Admin\Discounts;

use App\Data\Admin\Discounts\OrderContextData;
use App\Enums\Order\OrderStatusEnum;
use App\Models\DiscountPromotion;
use App\Models\DiscountPromotionUsage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Enforces the per-customer promotion limit at checkout.
 *
 * A customer's active usage of a Promotion is the count of their usage rows
 * whose order is not cancelled/failed (pending/processing reserve, completed/
 * refunded consume). When active usage reaches `usage_limit_per_customer`,
 * checkout is rejected with an explicit message that names a blocking pending
 * order when one exists.
 *
 * Must run inside a DB transaction: each applicable promotion row is locked
 * for update so concurrent checkouts cannot both slip past the limit.
 */
final readonly class ValidatePromotionPerCustomerLimitAction
{
    public function handle(OrderContextData $context): void
    {
        $customerId = $context->customer?->id;
        if (! $customerId) {
            return;
        }

        foreach ($context->applied_cart_discounts as $discount) {
            $promotionId = $discount['promotion_id'] ?? null;
            if (! $promotionId) {
                continue;
            }

            // Serialize concurrent per-customer checks for this promotion.
            $promotion = DiscountPromotion::query()
                ->whereKey($promotionId)
                ->lockForUpdate()
                ->first();
            if (! $promotion || $promotion->usage_limit_per_customer === null) {
                continue;
            }

            $activeUsage = DiscountPromotionUsage::query()
                ->where('discount_promotion_id', $promotionId)
                ->where('customer_id', $customerId)
                ->whereHas('order', fn (Builder $query): Builder => $query->whereNotIn('status', [
                    OrderStatusEnum::CANCELLED->value,
                    OrderStatusEnum::FAILED->value,
                ]))
                ->count();

            if ($activeUsage < $promotion->usage_limit_per_customer) {
                continue;
            }

            $blockingOrder = DiscountPromotionUsage::query()
                ->where('discount_promotion_id', $promotionId)
                ->where('customer_id', $customerId)
                ->whereHas('order', fn (Builder $query): Builder => $query->where('status', OrderStatusEnum::PENDING->value))
                ->with('order')
                ->first()
                ?->order;

            throw ValidationException::withMessages([
                'coupon' => [
                    $blockingOrder
                        ? __('messages.checkout.coupon_usage_pending_order_blocked', [
                            'order_id' => $blockingOrder->increment_id,
                        ])
                        : __('messages.checkout.coupon_usage_limit_reached'),
                ],
            ]);
        }
    }
}
