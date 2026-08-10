<?php

declare(strict_types=1);

namespace App\Data\Admin\Blog\Post;

use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Product\ProductableEnum;
use App\Helpers\JalaliDateHelper;
use App\Rules\ValidNormalizedJalaliDateRule;
use App\Traits\ValidatesMetaTags;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class BlogPostUpdateData extends Data
{
    use ValidatesMetaTags;

    public function __construct(
        public string $title,
        public ?string $slug,
        public string $body,
        public string $excerpt,
        public string $status,
        public ?int $author_id,
        #[WithCast(DateTimeInterfaceCast::class, 'Y-m-d H:i:s')]
        public ?Carbon $published_at,
        public ?bool $is_featured = false,
        /** @var array{id: int, type: string}|null */
        public ?array $main_productable = null,
        public ?string $meta_title = null,
        public ?string $meta_description = null,
        public ?string $meta_keywords = null,
        public ?array $category_ids = [],
        /** @var array<int, array{id: int, type: string}> */
        public ?array $related_productables = null,

        public ?array $media = [],
    ) {}

    public static function prepareForPipeline(array $properties): array
    {
        return JalaliDateHelper::toGregorian($properties, [
            'published_at' => 'Y-m-d H:i:s',
        ]);
    }

    public static function rules(?ValidationContext $context = null): array
    {
        $postId               = request()->route('post');
        $mainProductableType  = $context?->payload['main_productable']['type'] ?? null;
        $mainProductableTable = ProductableEnum::getTableFromType($mainProductableType);

        return array_merge([
            'title' => ['required', 'string', 'max:255'],
            'slug'  => [
                'nullable', 'string', 'max:255',
                Rule::unique('blog_posts', 'slug')->ignore($postId),
            ],
            'body'                => ['required', 'string'],
            'excerpt'             => ['required', 'string', 'max:500'],
            'status'              => ['required', 'string', Rule::enum(PublicationStatusEnum::class)],
            'author_id'           => ['nullable', 'integer', 'exists:staff,id'],
            'published_at'        => ['bail', 'nullable', new ValidNormalizedJalaliDateRule, 'date_format:Y-m-d H:i:s'],
            'is_featured'         => ['nullable', 'boolean'],
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

    /**
     * @codeCoverageIgnore
     *
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'related_productables.*.id.required'   => __('validation.custom.blog_post.related_productables.id.required'),
            'related_productables.*.id.integer'    => __('validation.custom.blog_post.related_productables.id.integer'),
            'related_productables.*.type.required' => __('validation.custom.blog_post.related_productables.type.required'),
            'media.cover.*.integer'                => __('validation.custom.blog_post.media.cover.integer'),
            'media.cover.*.exists'                 => __('validation.custom.blog_post.media.cover.exists'),
            'media.gallery.*.integer'              => __('validation.custom.blog_post.media.gallery.integer'),
            'media.gallery.*.exists'               => __('validation.custom.blog_post.media.gallery.exists'),
            'media.video.*.integer'                => __('validation.custom.blog_post.media.video.integer'),
            'media.video.*.exists'                 => __('validation.custom.blog_post.media.video.exists'),
        ];
    }

    /**
     * @codeCoverageIgnore
     */
    public function bodyParameters(): array
    {
        return [
            'title' => [
                'description' => 'The title of the blog post.',
                'example'     => 'How to Use Laravel Data',
            ],
            'slug' => [
                'description' => 'The slug for the blog post.',
                'example'     => 'how-to-use-laravel-data',
            ],
            'body' => [
                'description' => 'The main content of the blog post.',
                'example'     => 'This is the body of the blog post.',
            ],
            'excerpt' => [
                'description' => 'A short excerpt for the blog post.',
                'example'     => 'Learn how to use Laravel Data for DTOs.',
            ],
            'status' => [
                'description' => 'Publication status of the post.',
                'example'     => 'published',
            ],
            'author_id' => [
                'description' => 'ID of the author.',
                'example'     => 1,
            ],
            'published_at' => [
                'description' => 'Publish date in Jalali format.',
                'example'     => '1402-01-01 12:00:00',
            ],
            'is_featured' => [
                'description' => 'Whether the post is featured.',
                'example'     => true,
            ],
            'main_productable' => [
                'description' => 'Main productable object.',
                'example'     => ['id' => 1, 'type' => 'product'],
            ],
            'main_productable.id' => [
                'description' => 'ID of the main productable.',
                'example'     => 1,
            ],
            'main_productable.type' => [
                'description' => 'Type of the main productable.',
                'example'     => 'product',
            ],
            'category_ids' => [
                'description' => 'Array of category IDs.',
                'example'     => [1, 2],
            ],
            'category_ids.*' => [
                'description' => 'A category ID.',
                'example'     => 1,
            ],
            'related_productables' => [
                'description' => 'Array of related productable objects.',
                'example'     => [['id' => 2, 'type' => 'product']],
            ],
            'related_productables.*.id' => [
                'description' => 'ID of a related productable.',
                'example'     => 2,
            ],
            'related_productables.*.type' => [
                'description' => 'Type of a related productable.',
                'example'     => 'product',
            ],
            'media' => [
                'description' => 'Media object containing cover, gallery, and video.',
                'example'     => [
                    'cover'   => [1],
                    'gallery' => [2, 3],
                    'video'   => [4],
                ],
            ],
            'media.cover' => [
                'description' => 'Array of cover media IDs.',
                'example'     => [1],
            ],
            'media.gallery' => [
                'description' => 'Array of gallery media IDs.',
                'example'     => [2, 3],
            ],
            'media.video' => [
                'description' => 'Array of video media IDs.',
                'example'     => [4],
            ],
            'media.cover.*' => [
                'description' => 'A cover media ID.',
                'example'     => 1,
            ],
            'media.gallery.*' => [
                'description' => 'A gallery media ID.',
                'example'     => 2,
            ],
            'media.video.*' => [
                'description' => 'A video media ID.',
                'example'     => 4,
            ],
        ] + self::metaTagBodyParameters();
    }
}
