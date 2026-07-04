<?php

declare(strict_types=1);

namespace App\Data\Shop\Blog;

use App\Models\Blog\BlogPost;
use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Data;

final class BlogPostDetailData extends Data
{
    /**
     * @param  array<int, BlogCategoryCardData>  $categories
     * @param  array<string, array<int, string>>  $media
     */
    public function __construct(
        public string $title,
        public string $slug,
        public string $body,
        public ?string $excerpt = null,
        public ?AuthorData $author = null,
        public int $reviews_count = 0,
        public float $average_rating = 0.0,
        public ?Verta $published_at = null,
        public ?string $thumbnail_url = null,
        public int $read_time_minutes = 0,
        public bool $is_featured = false,
        public array $categories = [],
        public array $related_products = [],
        public array $media = [],
        public ?string $meta_title = null,
        public ?string $meta_description = null,
        public ?string $meta_keywords = null,
    ) {}

    public static function fromModel(BlogPost $post, array $relatedProducts = []): self
    {
        return new self(
            title: $post->title,
            slug: $post->slug,
            body: $post->body,
            excerpt: $post->excerpt,
            author: $post->author ? AuthorData::from(['name' => $post->author->name]) : null,
            reviews_count: $post->review_count,
            average_rating: (float) $post->average_rating,
            published_at: $post->published_at ? Verta::instance($post->published_at) : null,
            thumbnail_url: $post->thumbnail_url,
            read_time_minutes: $post->read_time_minutes,
            is_featured: $post->is_featured,
            categories: $post->categories->map(fn ($category): BlogCategoryCardData => BlogCategoryCardData::fromModel($category))->all(),
            related_products: $relatedProducts,
            media: $post->getAllMedia(),
            meta_title: $post->meta_title,
            meta_description: $post->meta_description,
            meta_keywords: $post->meta_keywords,
        );
    }
}
