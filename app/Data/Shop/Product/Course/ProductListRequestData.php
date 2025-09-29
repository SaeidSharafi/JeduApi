<?php

declare(strict_types=1);

namespace App\Data\Shop\Product\Course;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class ProductListRequestData extends Data
{
    public function __construct(
        public ?ProductFilterData $filter = null,
        public ?string $sortBy = 'created_at',
        public ?string $sortOrder = 'desc',

        public ?int $page = null,
        public ?int $per_page = 15,
    ) {}

    public static function rules(?ValidationContext $context = null): array
    {
        $filters = CourseFilterData::rules($context);
        foreach ($filters as $key => $rule) {
            $filters["filter.$key"] = $rule;
            unset($filters[$key]);
        }

        return [
            'filter'   => ['sometimes', 'array'],
            'filter.*' => ['sometimes'],
            ...$filters,
            'sortBy'    => ['sometimes', 'string', 'in:title,created_at'],
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
        return [
            'filter' => [
                'description' => 'Filter criteria for courses',
                'example'     => ['category_id' => 1, 'status' => 'published'],
            ],
            'sortBy'    => ['description' => 'Field to sort by', 'example' => 'created_at'],
            'sortOrder' => ['description' => 'Sort order (asc or desc)', 'example' => 'desc'],
            'page'      => ['description' => 'Page number for pagination', 'example' => 1],
            'per_page'  => ['description' => 'Number of items per page', 'example' => 15],
        ];
    }
}
