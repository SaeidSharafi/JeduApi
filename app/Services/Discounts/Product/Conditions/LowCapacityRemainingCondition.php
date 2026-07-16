<?php

namespace App\Services\Discounts\Product\Conditions;

use App\Contracts\Discounts\ProductDiscountConditionContract;
use App\Models\ProductDeliveryOption;
use App\Services\Discounts\Configs\LowCapacityRemainingData;
use Spatie\LaravelData\Data;

#[DiscountHandlerKey('low_capacity_remaining')]
class LowCapacityRemainingCondition implements ProductDiscountConditionContract
{
    public static function getConfigClass(): string
    {
        return LowCapacityRemainingData::class;
    }

    public function passes(ProductDeliveryOption $option, Data $configuration): bool
    {
        /** @var LowCapacityRemainingData $configuration */
        if (!$option->capacity || $option->capacity <= 0) {
            return false;
        }

        // enrolled_count is assumed to be aggregated/appended dynamically per the query architecture
        $ratio = ($option->enrolled_count ?? 0) / $option->capacity;

        return $ratio >= $configuration->threshold;
    }
}
