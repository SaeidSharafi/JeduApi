<?php

declare(strict_types=1);

namespace App\Data\Shop\Blog;

use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class BlogPostListRequestData extends Data
{
    public function __construct(
        public ?bool $is_featured = null,
        public ?string $category_slug = null,
        public ?string $sortBy = 'published_at',
        public ?string $sortOrder = 'desc',
        public ?int $page = null,
        public ?int $per_page = 15,
    ) {}

    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'is_featured'   => ['sometimes', 'boolean'],
            'category_slug' => ['sometimes', 'string', 'exists:blog_categories,slug'],
            'sortBy'        => ['sometimes', 'string', Rule::in(['created_at', 'published_at', 'popularity'])],
            'sortOrder'     => ['sometimes', 'string', 'in:asc,desc'],
            'page'          => ['sometimes', 'integer', 'min:1'],
            'per_page'      => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @codeCoverageIgnore
     */
    public function queryParameters(): array
    {
        return [
            'is_featured' => [
                'description' => 'Filter by featured posts',
                'example'     => true,
            ],
            'category_slug' => [
                'description' => 'Filter by category slug',
                'example'     => 'programming',
            ],
            'sortBy' => [
                'description' => 'Field to sort by (created_at, published_at, or popularity)',
                'example'     => 'published_at',
            ],
            'sortOrder' => [
                'description' => 'Sort order (asc or desc), defaults to desc, ignored if sortBy is popularity',
                'example'     => 'desc',
            ],
            'page' => [
                'description' => 'Page number for pagination',
                'example'     => 1,
            ],
            'per_page' => [
                'description' => 'Number of items per page',
                'example'     => 15,
            ],
        ];
    }
}
