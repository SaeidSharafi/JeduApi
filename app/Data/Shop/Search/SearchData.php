<?php

declare(strict_types=1);

namespace App\Data\Shop\Search;

use App\Enums\Product\ProductableEnum;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

/**
 * Search Data
 *
 * Used for both request validation and service layer processing.
 * This DTO flows through the entire search system.
 */
final class SearchData extends Data
{
    public function __construct(
        public string $q,
        public ?int $per_page = 15,
        public ?array $result_types = null,
        public ?string $productable_type = null,
        public ?bool $has_discount = null,
        public ?array $category_ids = null,
        public ?int $price_min = null,
        public ?int $price_max = null,
        public ?string $difficulty_level = null,
        public ?array $fulfillment_types = null,
    ) {}

    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'q'                   => ['required', 'string', 'max:255'],
            'per_page'            => ['sometimes', 'integer', 'min:1', 'max:100'],
            'result_types'        => ['sometimes', 'array'],
            'result_types.*'      => ['string', Rule::in(['product', 'blog_post'])],
            'productable_type'    => ['sometimes', 'string', Rule::enum(ProductableEnum::class)],
            'has_discount'        => ['sometimes', 'boolean'],
            'category_ids'        => ['sometimes', 'array'],
            'category_ids.*'      => ['integer'],
            'price_min'           => ['sometimes', 'integer', 'min:0'],
            'price_max'           => ['sometimes', 'integer', 'gt:price_min'],
            'difficulty_level'    => ['sometimes', 'string'],
            'fulfillment_types'   => ['sometimes', 'array'],
            'fulfillment_types.*' => ['string'],
        ];
    }

    /**
     * @codeCoverageIgnore
     */
    public function queryParameters(): array
    {
        return [
            'q' => [
                'description' => 'The search query',
                'example'     => 'laptop',
            ],
            'per_page' => [
                'description' => 'Number of results per page (1-100)',
                'example'     => 15,
            ],
            'result_types' => [
                'description' => 'Types of results to include: product, blog_post (returns both if not specified)',
                'example'     => ['product'],
            ],
            'filters' => [
                'description' => 'Search filters',
                'example'     => [
                    'productable_type'  => 'course',
                    'has_discount'      => true,
                    'category_ids'      => [1, 2, 3],
                    'price_min'         => 100000,
                    'price_max'         => 500000,
                    'difficulty_level'  => 'beginner',
                    'fulfillment_types' => ['digital', 'physical'],
                ],
            ],
        ];
    }
}
