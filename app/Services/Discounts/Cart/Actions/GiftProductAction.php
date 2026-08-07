<?php

declare(strict_types=1);

namespace App\Services\Discounts\Cart\Actions;

use App\Attributes\DiscountHandlerKey;
use App\Contracts\Discounts\DiscountActionContract;
use App\Data\Admin\Discounts\CalculatedOrderItemData;
use App\Data\Admin\Discounts\OrderContextData;
use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Models\ProductDeliveryOption;
use App\Services\Discounts\Configs\GiftProductData;
use Spatie\LaravelData\Data;

#[DiscountHandlerKey('gift_product')]
final class GiftProductAction implements DiscountActionContract
{
    public static function getConfigClass(): string
    {
        return GiftProductData::class;
    }

    public function apply(OrderContextData $context, Data $configuration): void
    {
        /** @var GiftProductData $configuration */

        // Check if the gift is already in the cart to prevent infinite loops or duplicates
        $alreadyGifted = $context->items->contains(function ($item) use ($configuration): bool {
            return $item->is_gift && $item->product_delivery_option->id === $configuration->product_delivery_option_id;
        });

        if ($alreadyGifted) {
            return;
        }

        $giftOption = ProductDeliveryOption::with('product')->find($configuration->product_delivery_option_id);

        if (! $giftOption) {
            return;
        }

        $context->items->push(new CalculatedOrderItemData(
            product_delivery_option: $giftOption,
            qty: 1,
            payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT,
            price: $giftOption->price,
            total: 0, // 100% free
            discount_amount: $giftOption->price,
            applied_discount_details: [
                ['type' => 'gift', 'promotion' => $context->evaluating_promotion?->name],
            ],
            is_gift: true
        ));

        // Adjust context subtotals so the "full value" audit trail reflects the gift
        $context->subtotal_all_items          += $giftOption->price;
        $context->subtotal_full_payment_items += $giftOption->price;
    }
}
