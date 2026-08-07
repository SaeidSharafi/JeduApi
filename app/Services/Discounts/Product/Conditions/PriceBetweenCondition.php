<?php

declare(strict_types=1);

namespace App\Services\Discounts\Product\Conditions;

use App\Attributes\DiscountHandlerKey;
use App\Contracts\Discounts\ProductDiscountConditionContract;
use App\Models\ProductDeliveryOption;
use App\Services\Discounts\Configs\PriceBetweenData;
use Spatie\LaravelData\Data;

#[DiscountHandlerKey('price_between')]
final class PriceBetweenCondition implements ProductDiscountConditionContract
{
    public static function getConfigClass(): string
    {
        return PriceBetweenData::class;
    }

    public function passes(ProductDeliveryOption $option, Data $configuration): bool
    {
        /** @var PriceBetweenData $configuration */
        if ($option->price < $configuration->min_price) {
            return false;
        }

        if ($configuration->max_price !== null && $option->price > $configuration->max_price) {
            return false;
        }

        return true;
    }
}
