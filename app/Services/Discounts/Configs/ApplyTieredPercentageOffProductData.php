<?php

namespace App\Services\Discounts\Configs;

use Spatie\LaravelData\Data;

class ApplyTieredPercentageOffProductData extends Data
{
    /** @var TierData[] */
    public function __construct(
        public array $tiers
    ) {}
}
