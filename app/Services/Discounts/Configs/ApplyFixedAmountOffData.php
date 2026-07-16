<?php

namespace App\Services\Discounts\Configs;

use Spatie\LaravelData\Data;

class ApplyFixedAmountOffData extends Data
{
    public function __construct(
        public int $amount
    ) {}
}
