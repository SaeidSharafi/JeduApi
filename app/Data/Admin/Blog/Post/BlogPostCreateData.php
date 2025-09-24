<?php

declare(strict_types=1);

namespace App\Data\Admin\Blog\Post;

use App\Data\Transformer\CarbonFromJalaliString;
use App\Enums\ProductableEnum;
use App\Enums\PublicationStatusEnum;
use App\Traits\ValidatesMetaTags;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class BlogPostCreateData extends Data
{
    use ValidatesMetaTags;

    public function __construct(
        public string $title,
        public ?string $slug,
        public string $body,
        public string $excerpt,
        public string $status,
        public ?int $author_id = null,
        #[WithCast(CarbonFromJalaliString::class, 'Y-m-d H:i:s')]
        public ?Carbon $published_at,
        public bool $is_featured = false,
        /** @var array{id: int, type: string}|null */
        public ?array $main_productable = null,
        public ?array $category_ids = [],
        /** @var array<int, array{id: int, type: string}> */
        public ?array $related_productables = null,
        public ?string $meta_title = null,
        public ?string $meta_description = null,
        public ?string $meta_keywords = null,
        public array $media = []
    ) {}

    public static function rules(?ValidationContext $context = null): array
    {
        $mainProductableType  = $context?->payload['main_productable']['type'] ?? null;
        $mainProductableTable = ProductableEnum::getTableFromType($mainProductableType);

        return array_merge([
            'title'               => ['required', 'string', 'max:255'],
            'slug'                => ['nullable', 'string', 'max:255', Rule::unique('blog_posts', 'slug')],
            'body'                => ['required', 'string'],
            'excerpt'             => ['required', 'string', 'max:500'],
            'status'              => ['required', 'string', Rule::enum(PublicationStatusEnum::class)],
            'author_id'           => ['nullable', 'integer', 'exists:staff,id'],
            'published_at'        => ['nullable', 'jdate:Y-m-d H:i:s'],
            'is_featured'         => ['required', 'boolean'],
            'main_productable'    => ['nullable', 'array'],
            'main_productable.id' => [
                'required_with:main_productable', 'integer', Rule::exists($mainProductableTable, 'id'),
            ],
            'main_productable.type' => [
                'required_with:main_productable', 'string', Rule::enum(ProductableEnum::class),
            ],
            'category_ids'                => ['nullable', 'array'],
            'category_ids.*'              => ['integer', 'exists:blog_categories,id'],
            'related_productables'        => ['nullable', 'array'],
            'related_productables.*.id'   => ['required', 'integer'], // You need to validate each object in the array
            'related_productables.*.type' => ['required', 'string', Rule::enum(ProductableEnum::class)],
            'media'                       => ['required', 'array'],
            'media.cover'                 => ['nullable', 'array'],
            'media.gallery'               => ['nullable', 'array'],
            'media.video'                 => ['nullable', 'array'],
            'media.cover.*'               => ['required', 'integer', 'exists:media,id'],
            'media.gallery.*'             => ['nullable', 'integer', 'exists:media,id'],
            'media.video.*'               => ['nullable', 'integer', 'exists:media,id'],
        ], self::metaTagValidationRules());
    }
}
