<?php

declare(strict_types=1);

namespace App\Contracts\Discounts;

use App\Models\ProductDeliveryOption;
use Spatie\LaravelData\Data;

interface ProductDiscountConditionContract
{
    /**
     * Get the configuration data class for this condition.
     */
    public static function getConfigClass(): string;

    /**
     * Determine if the product delivery option passes the condition.
     */
    public function passes(ProductDeliveryOption $option, Data $configuration): bool;
}
