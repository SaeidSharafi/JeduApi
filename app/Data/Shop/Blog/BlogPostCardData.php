<?php

declare(strict_types=1);

namespace App\Data\Shop\Blog;

use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Data;

final class BlogPostCardData extends Data
{
    // 'id'              => $entity->id,
    //                'title'           => $entity->title,
    //                'slug'            => $entity->slug,
    //                'excerpt'         => $entity->excerpt,
    //                'author_name'     => $entity->author?->name ?? 'Unknown',
    //                'reviews_count'   => $entity->reviews_count ?? 0,
    //                'average_rating'  => $entity->average_rating ?? 0.0,
    //                'published_at'    => data_get($entity, 'details_json.published_at') ? verta(data_get($entity,
    //                    'details_json.published_at'))->format('Y-m-d') : null,
    //                'thumbnail_url' => $entity->relationLoaded('media') ? $entity->firstMedia('main') : null,
    public function __construct(
        public int $id,
        public string $title,
        public string $slug,
        public ?string $excerpt = null,
        public ?AuthorData $author = null,
        public ?int $reviews_count = 0,
        public ?float $average_rating = 0.0,
        public ?Verta $published_at = null,
        public ?string $thumbnail_url = null,
    ) {}
}
