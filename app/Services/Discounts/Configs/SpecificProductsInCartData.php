<?php

namespace App\Services\Discounts\Configs;

use Spatie\LaravelData\Data;

class SpecificProductsInCartData extends Data
{
    public function __construct(
        public array $product_ids
    ) {}
}
