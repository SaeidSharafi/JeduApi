<?php

declare(strict_types=1);

namespace App\Data\Admin\Blog\Post;

use App\Contracts\ProductableContract;
use App\Contracts\ProductableDataContract;
use App\Data\Admin\Auth\StaffData;
use App\Data\Admin\Blog\Category\BlogCategoryData;
use App\Data\Admin\MediaData;
use App\Data\Casts\ProductableCast;
use App\Data\Transformer\ProductableTransformer;
use App\Models\Blog\BlogPost;
use Hekmatinasser\Verta\Verta;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Support\EloquentCasts\DataCollectionEloquentCast;

final class BlogPostData extends Data
{
    public function __construct(
        public int $id,
        public string $title,
        public string $slug,
        public string $body,
        public string $excerpt,
        public int $author_id,
        public string $status,
        public ?Verta $published_at = null,
        public int $read_time_minutes,
        public bool $is_featured,
        public ?string $meta_title = null,
        public ?string $meta_description = null,
        public ?string $meta_keywords = null,
        #[WithCast(ProductableCast::class, short: true)]
        public ?ProductableDataContract $main_productable = null,
        #[DataCollectionOf(BlogCategoryData::class)]
        public ?Collection $categories = null,
        #[WithTransformer(ProductableTransformer::class, short: true)]
        #[MapOutputName('related_productables')]
        public ?Collection $related_productables = null,
        public ?StaffData $author = null,
        public ?array $media = null,
        public ?Verta $created_at = null,
        public ?Verta $updated_at = null,
    ) {
    }

    public static function fromModel(BlogPost $blogPost): self
    {

        return self::from([
            ...$blogPost->toArray(),
            'author'               => $blogPost->author ? StaffData::from($blogPost->author) : null,
            'categories'           => $blogPost->categories,
            'related_productables' => $blogPost->related_productables ?: null,
            'main_productable'     => $blogPost->mainProductable,
            'media'           => $blogPost->getAllMedia(),
        ]);
    }
}
