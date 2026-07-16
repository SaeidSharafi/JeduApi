<?php

namespace App\Services\Discounts\Cart\Conditions;

use App\Data\Admin\Discounts\OrderContextData;
use App\Services\Discounts\Configs\SpecificProductsInCartData;
use App\Contracts\Discounts\DiscountConditionContract;
use App\Attributes\DiscountHandlerKey;
use Spatie\LaravelData\Data;

#[DiscountHandlerKey('specific_products_in_cart')]
class SpecificProductsInCartCondition implements DiscountConditionContract
{
    public static function getConfigClass(): string { return SpecificProductsInCartData::class; }

    public function passes(OrderContextData $context, Data $configuration): bool
    {
        /** @var SpecificProductsInCartData $configuration */
        $cartProductIds = $context->items->pluck('product_delivery_option.product_id')->toArray();
        return count(array_intersect($cartProductIds, $configuration->product_ids)) > 0;
    }
}
