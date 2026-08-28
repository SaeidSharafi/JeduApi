<?php

declare(strict_types=1);

namespace App\Services\Discounts\Product\Actions;

use App\Attributes\DiscountHandlerKey;
use App\Contracts\Discounts\ProductDiscountActionContract;
use App\Models\ProductDeliveryOption;
use App\Services\Discounts\Configs\ApplyTieredPercentageOffProductData;
use Spatie\LaravelData\Data;

#[DiscountHandlerKey('apply_tiered_percentage_off_product')]
final class ApplyTieredPercentageOffProductAction implements ProductDiscountActionContract
{
    public static function getConfigClass(): string
    {
        return ApplyTieredPercentageOffProductData::class;
    }

    public function apply(ProductDeliveryOption $option, Data $configuration): int
    {
        /** @var ApplyTieredPercentageOffProductData $configuration */
        $applicablePercentage = 0;

        foreach ($configuration->tiers as $tier) {
            if ($option->price >= $tier->min_amount && $tier->percentage > $applicablePercentage) {
                $applicablePercentage = $tier->percentage;
            }
        }

        if ($applicablePercentage > 0) {
            return (int) ($option->price * ($applicablePercentage / 100));
        }

        return 0;
    }
}
