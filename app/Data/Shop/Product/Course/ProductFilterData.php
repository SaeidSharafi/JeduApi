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
        public ?string $search = null,
        public ?array $category_ids = null, // Use IDs for global search
        public ?string $type = null, // Allow optional filtering by type
        public ?int $min_price = null,
        public ?int $max_price = null,
        public ?bool $with_discounts = null,
    ) {}

    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'search'         => ['sometimes', 'string', 'max:255'],
            'category_ids'   => ['sometimes', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'type'           => ['sometimes', 'string', Rule::enum(ProductableEnum::class)],
            'min_price'      => ['sometimes', 'integer', 'min:0'],
            'max_price'      => ['sometimes', 'integer', 'gt:min_price'],
            'with_discounts' => ['sometimes', 'boolean'],
        ];
    }
}
