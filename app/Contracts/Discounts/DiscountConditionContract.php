<?php

declare(strict_types=1);

namespace App\Contracts\Discounts;

use App\Data\Admin\Discounts\OrderContextData;
use Spatie\LaravelData\Data;

interface DiscountConditionContract
{
    /**
     * Get the configuration data class for this condition.
     */
    public static function getConfigClass(): string;

    public function passes(OrderContextData $context, Data $configuration): bool;
}
