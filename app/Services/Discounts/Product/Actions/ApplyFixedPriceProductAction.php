<?php

namespace App\Services\Discounts\Product\Actions;

use App\Attributes\DiscountHandlerKey;
use App\Contracts\Discounts\ProductDiscountActionContract;
use App\Models\ProductDeliveryOption;
use App\Services\Discounts\Configs\ApplyFixedPriceProductData;
use Spatie\LaravelData\Data;

#[DiscountHandlerKey('apply_fixed_price_product')]
class ApplyFixedPriceProductAction implements ProductDiscountActionContract
{
    public static function getConfigClass(): string
    {
        return ApplyFixedPriceProductData::class;
    }

    public function apply(ProductDeliveryOption $option, Data $configuration): int
    {
        /** @var ApplyFixedPriceProductData $configuration */
        if ($option->price <= $configuration->fixed_price) {
            return 0; // The fixed price is higher or equal to the base price, no discount
        }

        return $option->price - $configuration->fixed_price;
    }
}
