<?php

namespace App\Services\Discounts\Configs;

use Spatie\LaravelData\Data;

class UserNeverPurchasedCategoryData extends Data
{
    public function __construct(
        public array $category_ids
    ) {}
}
