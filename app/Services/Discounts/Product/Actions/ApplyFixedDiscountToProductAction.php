<?php

declare(strict_types=1);

namespace App\Services\Discounts\Product\Actions;

use App\Attributes\DiscountHandlerKey;
use App\Contracts\Discounts\ProductDiscountActionContract;
use App\Models\ProductDeliveryOption;
use App\Services\Discounts\Configs\ApplyFixedDiscountConfigData;
use Spatie\LaravelData\Data;

#[DiscountHandlerKey('apply_fixed_discount_product')]
final class ApplyFixedDiscountToProductAction implements ProductDiscountActionContract
{
    public static function getConfigClass(): string
    {
        return ApplyFixedDiscountConfigData::class;
    }

    public function apply(ProductDeliveryOption $option, Data $configuration): int
    {
        // @codeCoverageIgnoreStart
        if (!$configuration instanceof ApplyFixedDiscountConfigData) {
            return $option->price;
        }
        // @codeCoverageIgnoreEnd

        $discountedPrice = $option->price - $configuration->amount;

        // Ensure price doesn't go below 0
        return max($discountedPrice, 0);
    }
}
