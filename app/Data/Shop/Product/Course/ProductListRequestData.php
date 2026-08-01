<?php

declare(strict_types=1);

namespace App\Data\Shop\Product\Course;

use App\Enums\Product\ProductableEnum;
use App\Enums\Product\ProductSortFieldEnum;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class ProductListRequestData extends Data
{
    public function __construct(
        public ?ProductFilterData $filter = null,
        public ?string $q = null,
        public ?string $type = null,
        public ?string $sortBy = 'created_at',
        public ?string $sortOrder = 'desc',

        public ?int $page = null,
        public ?int $per_page = 15,
    ) {}

    public static function rules(?ValidationContext $context = null): array
    {
        $filters = ProductFilterData::rules($context, 'filter.');

        return [
            'q'        => ['sometimes', 'string', 'max:255'],
            'type'     => ['sometimes', 'string', Rule::enum(ProductableEnum::class)],
            'filter'   => ['sometimes', 'array'],
            'filter.*' => ['sometimes'],
            ...$filters,
            'sortBy'    => ['sometimes', 'string', Rule::in(ProductSortFieldEnum::ALLOWED)],
            'sortOrder' => ['sometimes', 'string', 'in:asc,desc'],
            'page'      => ['sometimes', 'integer', 'min:1'],
            'per_page'  => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @codeCoverageIgnore
     */
    public function queryParameters(): array
    {
        $parameters = ProductFilterData::queryParameters('filter.');

        return [
            'q'    => ['description' => 'Search query string', 'example' => 'laravel'],
            'type' => [
                'description' => 'Filter by product type (e.g., course, ebook)',
                'example'     => ProductableEnum::COURSE->value,
            ],
            'filter' => [
                'description' => 'Filter criteria for courses',
                'example'     => ['category_id' => 1, 'status' => 'published'],
            ],
            ...$parameters,
            'sortBy'    => ['description' => 'Field to sort by', 'example' => 'created_at'],
            'sortOrder' => ['description' => 'Sort order (asc or desc)', 'example' => 'desc'],
            'page'      => ['description' => 'Page number for pagination', 'example' => 1],
            'per_page'  => ['description' => 'Number of items per page', 'example' => 15],
        ];
    }
}
