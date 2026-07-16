<?php

namespace App\Services\Discounts\Configs;

use Spatie\LaravelData\Data;

class GiftProductData extends Data
{
    public function __construct(
        public int $product_delivery_option_id
    ) {}
}
