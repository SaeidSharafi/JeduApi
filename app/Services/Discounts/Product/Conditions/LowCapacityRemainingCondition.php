<?php

declare(strict_types=1);

namespace App\Services\Discounts\Product\Conditions;

use App\Attributes\DiscountHandlerKey;
use App\Contracts\Discounts\ProductDiscountConditionContract;
use App\Models\ProductDeliveryOption;
use App\Services\Discounts\Configs\LowCapacityRemainingData;
use Spatie\LaravelData\Data;

#[DiscountHandlerKey('low_capacity_remaining')]
final class LowCapacityRemainingCondition implements ProductDiscountConditionContract
{
    public static function getConfigClass(): string
    {
        return LowCapacityRemainingData::class;
    }

    public function passes(ProductDeliveryOption $option, Data $configuration): bool
    {
        /** @var LowCapacityRemainingData $configuration */
        if (! $option->capacity || $option->capacity <= 0) {
            return false;
        }

        // only enrolled_count should be considered, becuase if we add reserved_count, malicious user can
        //  easly trigger this condition by making fake pedning orders
        $ratio = (($option->enrolled_count ?? 0) / $option->capacity);

        return $ratio >= $configuration->threshold;
    }
}
