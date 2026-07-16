<?php

namespace App\Services\Discounts\Configs;

use Spatie\LaravelData\Data;

class ApplyFixedPriceProductData extends Data
{
    public function __construct(
        public int $fixed_price
    ) {}
}
