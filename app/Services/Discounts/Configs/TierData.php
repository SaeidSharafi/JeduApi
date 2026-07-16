<?php

namespace App\Services\Discounts\Configs;

use Spatie\LaravelData\Data;

class TierData extends Data
{
    public function __construct(
        public int $min_amount,
        public float $percentage
    ) {}
}
