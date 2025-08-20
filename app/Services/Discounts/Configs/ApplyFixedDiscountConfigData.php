<?php

declare(strict_types=1);

namespace App\Services\Discounts\Configs;

use Spatie\LaravelData\Data;

final class ApplyFixedDiscountConfigData extends Data
{

    public function __construct(
        public int $amount // Amount to subtract from price (in cents)
    ) {}
}
