<?php

declare(strict_types=1);

use App\Data\Admin\Discounts\CalculatedOrderItemData;
use App\Data\Admin\Discounts\OrderContextData;
use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Models\DiscountCoupon;
use App\Models\DiscountPromotion;
use App\Models\DiscountPromotionUsage;
use App\Models\Order;
use App\Models\ProductDeliveryOption;
use App\Models\User;
use App\Services\Discounts\Cart\Conditions\CartValueTierCondition;
use App\Services\Discounts\Configs\CartValueTierConditionConfigData;
use App\Services\Discounts\Configs\TierData;

/*
 * Shared discount fixtures. Pest loads these helpers suite-wide, so every name
 * is prefixed with `discount`: a bare `contextOf()` in two files would be a
 * fatal redeclaration that only surfaces in whichever file loads second.
 */

if (! function_exists('discountFullPaymentLine')) {
    /**
     * A fully paid cart line. Pass `$total` when the line already carries a
     * product discount, so price and effective worth differ.
     */
    function discountFullPaymentLine(int $price, int $qty = 1, ?int $total = null): CalculatedOrderItemData
    {
        return new CalculatedOrderItemData(
            product_delivery_option: ProductDeliveryOption::factory()->make(['price' => $price]),
            qty: $qty,
            payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT,
            price: $price,
            total: $total ?? $price * $qty,
        );
    }
}

if (! function_exists('discountPrepaymentLine')) {
    /**
     * A deposit line: worth only its prepayment amount, never the course price.
     */
    function discountPrepaymentLine(int $price, int $prepaymentAmount, int $qty = 1): CalculatedOrderItemData
    {
        return new CalculatedOrderItemData(
            product_delivery_option: ProductDeliveryOption::factory()->make([
                'price'             => $price,
                'prepayment_amount' => $prepaymentAmount,
            ]),
            qty: $qty,
            payment_type: OrderItemPaymentTypeEnum::PRE_PAYMENT,
            price: $price,
            total: $prepaymentAmount * $qty,
        );
    }
}

if (! function_exists('discountBundleLine')) {
    /**
     * A reviewed Bundle line: a full-payment line no cart markdown may touch.
     */
    function discountBundleLine(int $price, int $qty = 1): CalculatedOrderItemData
    {
        return new CalculatedOrderItemData(
            product_delivery_option: ProductDeliveryOption::factory()->make(['price' => $price]),
            qty: $qty,
            payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT,
            price: $price,
            total: $price * $qty,
            is_bundle: true,
        );
    }
}

if (! function_exists('discountOrderContext')) {
    /**
     * The calculation context a set of lines produces at build time, with the
     * same snapshots `PromotionService::buildOrderContext()` writes.
     *
     * @param  array<int, CalculatedOrderItemData>  $items
     * @param  int|null  $baseSubtotalAllItems  Override when a test needs the base-priced all-lines total to differ from the effective one.
     */
    function discountOrderContext(
        array $items,
        ?User $customer = null,
        ?int $baseSubtotalAllItems = null,
    ): OrderContextData {
        $lines = collect($items);

        return OrderContextData::from([
            'customer'                    => $customer ?? User::factory()->make(),
            'items'                       => $items,
            'subtotal_full_payment_items' => $lines
                ->where('payment_type', OrderItemPaymentTypeEnum::FULL_PAYMENT)
                ->sum('total'),
            'subtotal_all_items'                    => $baseSubtotalAllItems ?? $lines->sum('total'),
            'effective_subtotal_all_items'          => $lines->sum('total'),
            'effective_subtotal_discountable_items' => $lines
                ->reject(fn (CalculatedOrderItemData $item): bool => $item->is_bundle
                    || $item->payment_type === OrderItemPaymentTypeEnum::PRE_PAYMENT)
                ->sum('total'),
        ]);
    }
}

if (! function_exists('discountDepositOnlyContext')) {
    /**
     * A deposit-only cart: one line paid in full is impossible here, so no
     * registered cart action may mark anything down. A tier selection is
     * published so the config-free consuming action is genuinely driven.
     */
    function discountDepositOnlyContext(int $prepaymentAmount = 25000, int $price = 100000): OrderContextData
    {
        $context = discountOrderContext(
            [discountPrepaymentLine(price: $price, prepaymentAmount: $prepaymentAmount, qty: 2)],
            customer: User::factory()->create(),
            baseSubtotalAllItems: $price * 2,
        );

        $context->evaluating_promotion = DiscountPromotion::factory()->create();

        $context->publishSelection(
            CartValueTierCondition::HANDLER_KEY,
            new TierData(min_amount: 0, percentage: 10.0),
        );

        return $context;
    }
}

if (! function_exists('discountTierConditionConfig')) {
    /**
     * The demo ladder: 5% from 3,000,000 and 8% from 5,000,000.
     */
    function discountTierConditionConfig(): CartValueTierConditionConfigData
    {
        return new CartValueTierConditionConfigData(tiers: [
            new TierData(min_amount: 3000000, percentage: 5),
            new TierData(min_amount: 5000000, percentage: 8),
        ]);
    }
}

if (! function_exists('discountReserveUsageSlot')) {
    /**
     * One reservation row holding a promotion (and optionally a coupon) slot,
     * placed by a freshly created order in the given status.
     */
    function discountReserveUsageSlot(
        DiscountPromotion $promotion,
        ?DiscountCoupon $coupon = null,
        OrderStatusEnum $status = OrderStatusEnum::PENDING,
    ): DiscountPromotionUsage {
        $holdingOrder = Order::factory()->create(['status' => $status->value]);

        return DiscountPromotionUsage::create([
            'discount_promotion_id' => $promotion->id,
            'discount_coupon_id'    => $coupon?->id,
            'customer_id'           => $holdingOrder->customer_id,
            'order_id'              => $holdingOrder->id,
        ]);
    }
}

if (! function_exists('discountReserveUsageSlots')) {
    /**
     * `$count` separate holding orders, so a cap can be filled to its limit.
     */
    function discountReserveUsageSlots(
        DiscountPromotion $promotion,
        int $count,
        OrderStatusEnum $status = OrderStatusEnum::PENDING,
    ): void {
        for ($i = 0; $i < $count; $i++) {
            discountReserveUsageSlot($promotion, status: $status);
        }
    }
}
