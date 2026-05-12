<?php

declare(strict_types=1);

namespace App\Data\Shop\Search;

use App\Data\Shop\Product\Course\ProductFilterData;
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
        public ?string $q,
        public ?int $per_page = 15,
        public ?array $result_types = null,
        public ?string $productable_type = null,
        public ?ProductFilterData $filter = null,
    ) {}

    public static function rules(?ValidationContext $context = null): array
    {
        $filters = ProductFilterData::rules($context, 'filter.');

        return [
            'q'                => ['sometimes', 'string', 'max:255'],
            'per_page'         => ['sometimes', 'integer', 'min:1', 'max:100'],
            'result_types'     => ['sometimes', 'array'],
            'result_types.*'   => ['string', Rule::in(['product', 'blog_post'])],
            'productable_type' => ['sometimes', 'string', Rule::enum(ProductableEnum::class)],
            'filter'           => ['sometimes', 'array'],
            'filter.*'         => ['sometimes'],
            ...$filters,
        ];
    }

    /**
     * @codeCoverageIgnore
     */
    public function queryParameters(): array
    {
        $parameters = ProductFilterData::queryParameters('filter.');

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
            'result_types.*' => [
                'description' => 'Types of results to include: product, blog_post (returns both if not specified)',
                'example'     => 'product',
            ],
            'productable_type' => [
                'description' => 'Filter results by productable type (e.g., course, bundle)',
                'example'     => ProductableEnum::COURSE->value,
            ],
            'filter' => [
                'description' => 'Filter criteria for courses',
                'example'     => ['category_id' => 1, 'status' => 'published'],
            ],
            ...$parameters,
        ];
    }
}
