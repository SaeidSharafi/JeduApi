<?php

declare(strict_types=1);

use App\Data\Admin\Discounts\CalculatedOrderItemData;
use App\Data\Admin\Discounts\OrderContextData;
use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Models\DiscountPromotion;
use App\Models\ProductDeliveryOption;
use App\Models\User;
use Illuminate\Support\Collection;

if (! function_exists('discountRewardContext')) {
    /**
     * Build the calculation context a reward cart action receives while a cart
     * is being priced, with no order attached yet.
     *
     * @param  Collection<int, CalculatedOrderItemData>|null  $items
     */
    function discountRewardContext(User $customer, DiscountPromotion $promotion, ?Collection $items = null): OrderContextData
    {
        return new OrderContextData(
            customer: $customer,
            items: $items ?? collect([]),
            subtotal_full_payment_items: 10000,
            subtotal_all_items: 10000,
            evaluating_promotion: $promotion,
        );
    }
}

if (! function_exists('discountRewardFullPaymentItem')) {
    /**
     * A fully paid cart line that reward per-item counting must include.
     */
    function discountRewardFullPaymentItem(ProductDeliveryOption $option, int $qty, int $price): CalculatedOrderItemData
    {
        return new CalculatedOrderItemData(
            product_delivery_option: $option,
            qty: $qty,
            payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT,
            price: $price,
            total: $price * $qty,
        );
    }
}
