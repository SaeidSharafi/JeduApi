<?php

namespace App\Services\Discounts\Configs;

use Spatie\LaravelData\Data;

class PriceBetweenData extends Data
{
    public function __construct(
        public int $min_price,
        public ?int $max_price = null
    ) {}
}
