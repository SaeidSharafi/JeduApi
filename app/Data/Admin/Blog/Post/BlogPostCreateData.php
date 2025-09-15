<?php

declare(strict_types=1);

namespace App\Data\Admin\Blog\Post;

use App\Enums\ProductableEnum;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;
use App\Enums\PublicationStatusEnum;

final class BlogPostCreateData extends Data
{
    public function __construct(
        public string $title,
        public ?string $slug = null,
        public string $body,
        public string $excerpt,
        public int $author_id,
        public string $status,
        public ?string $published_at = null,
        public bool $is_featured = false,
        /** @var array{id: int, type: string}|null */
        public ?array $main_productable = null,
        public ?array $category_ids = [],
        /** @var array<int, array{id: int, type: string}> */
        public array $related_productables,
        public ?int $main_media = null,
    ) {
    }

    public static function rules(?ValidationContext $context = null): array
    {
        $mainProductableType = $context?->payload['main_productable']['type'] ?? null;
        $mainProductableTable = ProductableEnum::getTableFromType($mainProductableType);

        return [
            'title'                       => ['required', 'string', 'max:255'],
            'slug'                        => ['nullable', 'string', 'max:255', Rule::unique('blog_posts', 'slug')],
            'body'                        => ['required', 'string'],
            'excerpt'                     => ['required', 'string', 'max:500'],
            'author_id'                   => ['required', 'integer', 'exists:staff,id'],
            'status'                      => ['required', 'string', Rule::enum(PublicationStatusEnum::class)],
            'published_at'                => ['nullable', 'date'],
            'is_featured'                 => ['boolean'],
            'main_productable'            => ['nullable', 'array'],
            'main_productable.id'         => [
                'required_with:main_productable', 'integer', Rule::exists($mainProductableTable, 'id')
            ],
            'main_productable.type'       => [
                'required_with:main_productable', 'string', Rule::enum(ProductableEnum::class)
            ],
            'category_ids'                => ['nullable', 'array'],
            'category_ids.*'              => ['integer', 'exists:blog_categories,id'],
            'related_productables'        => ['nullable', 'array'],
            'related_productables.*.id'   => ['required', 'integer'], // You need to validate each object in the array
            'related_productables.*.type' => ['required', 'string', Rule::enum(ProductableEnum::class)],
            'main_media'                  => ['nullable', 'integer', 'exists:media,id'],
        ];
    }
}
