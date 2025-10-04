<?php

declare(strict_types=1);

namespace App\Data\Shop\Blog;

use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Data;

final class BlogPostCardData extends Data
{
    public function __construct(
        public string $title,
        public string $slug,
        public ?string $excerpt = null,
        public ?string $body = null,
        public ?AuthorData $author = null,
        public ?int $reviews_count = 0,
        public ?float $average_rating = 0.0,
        public ?Verta $published_at = null,
        public ?string $thumbnail_url = null,
    ) {}
}
