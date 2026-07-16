<?php

namespace App\Services\Discounts\Configs;

use Spatie\LaravelData\Data;

class LowCapacityRemainingData extends Data
{
    public function __construct(
        public float $threshold // e.g. 0.8 for 80% full
    ) {}
}
