<?php

declare(strict_types=1);

namespace App\Services\Discounts\Product\Conditions;

use App\Attributes\DiscountHandlerKey;
use App\Contracts\Discounts\ProductDiscountConditionContract;
use App\Models\ProductDeliveryOption;
use App\Services\Discounts\Configs\DeliveryMethodIsData;
use Spatie\LaravelData\Data;

#[DiscountHandlerKey('delivery_method_is')]
final class DeliveryMethodIsCondition implements ProductDiscountConditionContract
{
    public static function getConfigClass(): string
    {
        return DeliveryMethodIsData::class;
    }

    public function passes(ProductDeliveryOption $option, Data $configuration): bool
    {
        /** @var DeliveryMethodIsData $configuration */
        return in_array($option->delivery_method->value, $configuration->delivery_methods);
    }
}
