<?php

namespace App\Services\Discounts\Configs;

use Spatie\LaravelData\Data;

class DeliveryMethodIsData extends Data
{
    public function __construct(
        public array $delivery_methods
    ) {}
}
