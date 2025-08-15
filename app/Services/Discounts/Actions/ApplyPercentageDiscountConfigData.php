<?php

declare(strict_types=1);

namespace App\Services\Discounts\Actions;

use Spatie\LaravelData\Data;

final class ApplyPercentageDiscountConfigData extends Data
{
    public function __construct(
        public int $percentage, // e.g., 15 for 15%
    ) {}
}
