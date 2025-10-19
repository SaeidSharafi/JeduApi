<?php

declare(strict_types=1);

namespace App\Data\Shop\Blog;

use App\Models\Blog\BlogCategory;
use Spatie\LaravelData\Data;

final class BlogCategoryDetailData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public ?string $description = null,
        public ?string $icon = null,
        public int $posts_count = 0,
        public ?string $meta_title = null,
        public ?string $meta_description = null,
        public ?string $meta_keywords = null,
    ) {}

    public static function fromModel(BlogCategory $category): self
    {
        return new self(
            id: $category->id,
            name: $category->name,
            slug: $category->slug,
            description: $category->description,
            icon: $category->icon,
            posts_count: $category->posts_count ?? 0,
            meta_title: $category->meta_title,
            meta_description: $category->meta_description,
            meta_keywords: $category->meta_keywords,
        );
    }
}
