<?php

declare(strict_types=1);

namespace App\Data\Shop\Product\Course;

use App\Enums\Product\ProductableEnum;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class ProductFilterData extends Data
{
    public function __construct(
        public ?array $category_ids = null, // Use IDs for global search
        public ?string $type = null, // Allow optional filtering by type
        public ?int $min_price = null,
        public ?int $max_price = null,
        public ?bool $with_discounts = null,
    ) {}

    public static function rules(?ValidationContext $context = null, string $prefix = ''): array
    {
        return [
            $prefix.'category_ids'   => ['sometimes', 'array'],
            $prefix.'category_ids.*' => ['integer', 'exists:categories,id'],
            $prefix.'type'           => ['sometimes', 'string', Rule::enum(ProductableEnum::class)],
            $prefix.'min_price'      => ['sometimes', 'integer', 'min:0'],
            $prefix.'max_price'      => ['sometimes', 'integer', "gt:{$prefix}min_price"],
            $prefix.'with_discounts' => ['sometimes', 'boolean'],
        ];
    }
}
