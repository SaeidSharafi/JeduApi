<?php

declare(strict_types=1);

namespace App\Data\Admin\Blog\Category;

use App\Data\Admin\MediaData;
use App\Models\Blog\BlogCategory;
use Hekmatinasser\Verta\Verta;
use Plank\Mediable\Mediable;
use Spatie\LaravelData\Data;

final class BlogCategoryData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public ?string $description,
        public ?int $parent_id,
        public ?string $meta_title = null,
        public ?string $meta_description = null,
        public ?string $meta_keywords = null,
        public ?MediaData $icon = null,
        public ?int $posts_count = null,
        public ?Verta $created_at = null,
        public ?Verta $updated_at = null
    ) {
    }

    public static function fromModel(BlogCategory $category): self
    {
        $media = $category->firstMedia('icon');
        return self::from(
            [
                'id'               => $category->id,
                'name'             => $category->name,
                'slug'             => $category->slug,
                'description'      => $category->description,
                'parent_id'        => $category->parent_id,
                'meta_title'       => $category->meta_title,
                'meta_description' => $category->meta_description,
                'meta_keywords'    => $category->meta_keywords,
                'icon'             => $media,
                'posts_count'      => $category->posts_count ?? null,
            ]
        );
    }
}
