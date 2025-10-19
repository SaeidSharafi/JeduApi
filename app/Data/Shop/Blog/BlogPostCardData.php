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
    ) {
    }
}
