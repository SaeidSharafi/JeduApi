<?php

declare(strict_types=1);

namespace App\Data\Shop\Blog;

use App\Models\Blog\BlogCategory;
use Spatie\LaravelData\Data;

final class BlogCategoryCardData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public ?string $description = null,
        public ?string $icon = null,
        public int $posts_count = 0,
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
        );
    }
}
