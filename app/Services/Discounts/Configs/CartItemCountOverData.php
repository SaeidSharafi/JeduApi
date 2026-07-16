<?php

namespace App\Services\Discounts\Configs;

use Spatie\LaravelData\Data;

class CartItemCountOverData extends Data
{
    public function __construct(
        public int $min_count,
        public bool $count_quantities = false // false = distinct items, true = sum of quantities
    ) {}
}
