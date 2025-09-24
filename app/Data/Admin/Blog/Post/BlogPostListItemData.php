<?php

declare(strict_types=1);

namespace App\Data\Admin\Blog\Post;

use App\Data\Admin\Auth\StaffData;
use App\Models\Blog\BlogPost;
use Hekmatinasser\Verta\Verta;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

final class BlogPostListItemData extends Data
{
    public function __construct(
        public int $id,
        public string $title,
        public string $slug,
        public string $excerpt,
        public int $author_id,
        public string $status,
        public ?Verta $published_at,
        public int $read_time_minutes,
        public bool $is_featured,
        public ?string $meta_title = null,
        public ?string $meta_description = null,
        public ?string $meta_keywords = null,
        public ?Collection $categories = null,
        public ?StaffData $author = null,
        public ?string $thumbnail_url = null,
        public ?Verta $created_at = null,
        public ?Verta $updated_at = null,
    ) {}

    public static function fromModel(BlogPost $blogPost): self
    {

        return self::from([
            ...$blogPost->toArray(),
            'author'     => $blogPost->author ? StaffData::from($blogPost->author) : null,
            'categories' => $blogPost->categories,
        ]);
    }
}
