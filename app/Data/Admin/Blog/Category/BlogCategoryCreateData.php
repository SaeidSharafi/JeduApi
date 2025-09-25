<?php

declare(strict_types=1);

namespace App\Data\Admin\Blog\Category;

use App\Traits\ValidatesMetaTags;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class BlogCategoryCreateData extends Data
{
    use ValidatesMetaTags;

    public function __construct(
        public string $name,
        public ?string $slug = null,
        public ?string $description = null,
        public ?int $parent_id = null,
        public ?int $icon = null,
    ) {}

    public static function rules(?ValidationContext $context = null): array
    {
        return array_merge([
            'name'        => ['required', 'string', 'max:255'],
            'slug'        => ['nullable', 'string', 'max:255', Rule::unique('blog_categories', 'slug')],
            'description' => ['nullable', 'string'],
            'parent_id'   => ['nullable', 'integer', 'exists:blog_categories,id'],
            'icon'        => ['nullable', 'integer:', 'exists:media,id'],
        ], self::metaTagValidationRules());
    }

    /**
     * @codeCoverageIgnore
     */
    public function bodyParameters(): array
    {
        return [
            'name' => [
                'description' => 'The name of the blog category.',
                'example'     => 'Tech News',
            ],
            'slug' => [
                'description' => 'The slug for the category.',
                'example'     => 'tech-news',
            ],
            'description' => [
                'description' => 'A description for the category.',
                'example'     => 'Latest updates in technology.',
            ],
            'parent_id' => [
                'description' => 'ID of the parent category.',
                'example'     => 1,
            ],
            'icon' => [
                'description' => 'ID of the icon media.',
                'example'     => 10,
            ],
        ] + self::metaTagBodyParameters();
    }

    /**
     * Helper for meta tag body parameters for Scribe documentation.
     */
    private static function metaTagBodyParameters(): array
    {
        return [
            'meta_title' => [
                'description' => 'SEO meta title for the category.',
                'example'     => 'Tech News Category',
            ],
            'meta_description' => [
                'description' => 'SEO meta description for the category.',
                'example'     => 'Stay updated with the latest tech news.',
            ],
            'meta_keywords' => [
                'description' => 'SEO meta keywords for the category.',
                'example'     => 'tech,news,updates',
            ],
        ];
    }
}
