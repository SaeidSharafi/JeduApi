<?php

declare(strict_types=1);

namespace App\Data\Shop\Blog;

use App\Models\Blog\BlogPost;
use Hekmatinasser\Verta\Verta;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

final class BlogPostCardData extends Data
{
    /**
     * @param  array<int, BlogCategoryCardData>  $categories
     */
    public function __construct(
        public string $title,
        public string $slug,
        public ?string $excerpt = null,
        public ?AuthorData $author = null,
        public int $reviews_count = 0,
        public float $average_rating = 0.0,
        public ?Verta $published_at = null,
        public ?string $thumbnail_url = null,
        public int $read_time_minutes = 0,
        public bool $is_featured = false,
        #[DataCollectionOf(BlogCategoryCardData::class)]
        public ?Collection $categories = null,
        public array $media = [],
    ) {}

    public static function fromModel(BlogPost $post): self
    {
        return new self(
            title: $post->title,
            slug: $post->slug,
            excerpt: $post->excerpt,
            author: $post->author ? AuthorData::from(['name' => $post->author->name]) : null,
            reviews_count: $post->review_count,
            average_rating: (float) $post->average_rating,
            published_at: $post->published_at ? Verta::instance($post->published_at) : null,
            thumbnail_url: $post->thumbnail_url,
            read_time_minutes: $post->read_time_minutes,
            is_featured: $post->is_featured,
            categories: $post->relationLoaded('categories')
                ? $post->categories->map(fn ($category): \App\Data\Shop\Blog\BlogCategoryCardData => BlogCategoryCardData::fromModel($category))
                : null,
            media: $post->getAllMedia(urlOnly: true, onlyTags: ['cover']),
        );
    }
}
