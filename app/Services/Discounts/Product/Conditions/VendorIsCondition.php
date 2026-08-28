<?php

declare(strict_types=1);

namespace App\Services\Discounts\Product\Conditions;

use App\Contracts\Discounts\ProductDiscountConditionContract;
use App\Models\ProductDeliveryOption;
use App\Services\Discounts\Configs\VendorIsData;
use Spatie\LaravelData\Data;

final class VendorIsCondition implements ProductDiscountConditionContract
{
    public static function getConfigClass(): string
    {
        return VendorIsData::class;
    }

    public function passes(ProductDeliveryOption $option, Data $configuration): bool
    {
        /** @var VendorIsData $configuration */
        // Assuming the relation is loaded to prevent N+1 during indexation
        return in_array($option->product->vendor_id, $configuration->vendor_ids);
    }
}
