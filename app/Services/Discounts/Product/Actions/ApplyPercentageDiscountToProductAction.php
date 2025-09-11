<?php

declare(strict_types=1);

namespace App\Services\Discounts\Product\Actions;

use App\Attributes\DiscountHandlerKey;
use App\Contracts\Discounts\ProductDiscountActionContract;
use App\Models\ProductDeliveryOption;
use App\Services\Discounts\Configs\ApplyPercentageDiscountConfigData;
use Spatie\LaravelData\Data;

#[DiscountHandlerKey('apply_percentage_off_product')]
final class ApplyPercentageDiscountToProductAction implements ProductDiscountActionContract
{
    public static function getConfigClass(): string
    {
        return ApplyPercentageDiscountConfigData::class;
    }

    public function apply(ProductDeliveryOption $option, Data $configuration): int
    {
        if (! $configuration instanceof ApplyPercentageDiscountConfigData) {
            return $option->price;
        }
        $discountRate = $configuration->percentage / 100;
        $discounted   = (int) round($option->price * (1 - $discountRate));

        return max($discounted, 0);
    }
}
