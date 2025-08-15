<?php

declare(strict_types=1);

namespace App\Services\Discounts\Conditions;

use Spatie\LaravelData\Data;

final class ProductCategoryConditionConfigData extends Data
{
    public function __construct(
        /** @var int[] */
        public array $category_ids,
        public string $match_policy = 'any', // 'any' or 'all'
    ) {}
}
