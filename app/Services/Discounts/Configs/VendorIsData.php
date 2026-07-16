<?php

namespace App\Services\Discounts\Configs;

use Spatie\LaravelData\Data;

class VendorIsData extends Data
{
    public function __construct(
        public array $vendor_ids
    ) {}
}
