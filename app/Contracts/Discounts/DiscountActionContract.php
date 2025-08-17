<?php

declare(strict_types=1);

namespace App\Contracts\Discounts;

use App\Data\Admin\Discounts\OrderContextData;
use Spatie\LaravelData\Data;

interface DiscountActionContract
{
    /**
     * Get the configuration data class for this action.
     */
    public static function getConfigClass(): string;

    public function apply(OrderContextData $context, Data $configuration): void;
}
