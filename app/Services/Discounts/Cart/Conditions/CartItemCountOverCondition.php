<?php

declare(strict_types=1);

namespace App\Services\Discounts\Cart\Conditions;

use App\Attributes\DiscountHandlerKey;
use App\Contracts\Discounts\DiscountConditionContract;
use App\Data\Admin\Discounts\OrderContextData;
use App\Services\Discounts\Configs\CartItemCountOverData;
use Spatie\LaravelData\Data;

#[DiscountHandlerKey('cart_item_count_over')]
final class CartItemCountOverCondition implements DiscountConditionContract
{
    public static function getConfigClass(): string
    {
        return CartItemCountOverData::class;
    }

    public function passes(OrderContextData $context, Data $configuration): bool
    {
        /** @var CartItemCountOverData $configuration */
        $count = $configuration->count_quantities
            ? $context->items->sum('qty')
            : $context->items->count();

        return $count > $configuration->min_count;
    }
}
