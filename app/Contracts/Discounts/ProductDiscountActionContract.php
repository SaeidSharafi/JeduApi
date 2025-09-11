<?php

declare(strict_types=1);

namespace App\Contracts\Discounts;

use App\Models\ProductDeliveryOption;
use Spatie\LaravelData\Data;

interface ProductDiscountActionContract
{
    /**
     * Get the configuration data class for this action.
     */
    public static function getConfigClass(): string;

    /**
     * Apply the discount to the given product delivery option and return the discounted price.
     */
    public function apply(ProductDeliveryOption $option, Data $configuration): int;
}
