<?php

declare(strict_types=1);

namespace App\Actions\Admin\Blog\Post;

use App\Contracts\Cache\CacheStore;
use App\Data\Admin\Blog\Post\BlogPostUpdateData;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\MediaTagEnum;
use App\Enums\Product\ProductableEnum;
use App\Enums\System\CacheTag;
use App\Enums\System\StorefrontTag;
use App\Events\StorefrontCacheInvalidated;
use App\Models\Blog\BlogPost;
use Illuminate\Support\Str;
use Plank\Mediable\Media;

final readonly class UpdateBlogPostAction
{
    public function __construct(private CacheStore $cache) {}

    public function handle(BlogPost $post, BlogPostUpdateData $data): BlogPost
    {
        $slug          = $data->slug ?? Str::slug($data->title);
        $readTime      = $this->calculateReadTime($data->body);
        $media         = $data->media;
        $coverImageUrl = null;
        if ($cover = data_get($media, MediaTagEnum::COVER->value.'.0')) {
            $coverImageUrl = Media::find($cover)?->getUrl();
        }
        $isPublished = PublicationStatusEnum::PUBLISHED->value === $data->status;
        $publishedAt = $data->published_at;

        if ($publishedAt === null && $isPublished) {
            $publishedAt = $post->status === PublicationStatusEnum::PUBLISHED && $post->published_at !== null
                ? $post->published_at
                : now();
        }

        $postData = [
            'title'                 => $data->title,
            'slug'                  => $slug,
            'body'                  => $data->body,
            'excerpt'               => $data->excerpt,
            'author_id'             => $data->author_id ?? $post->author_id,
            'status'                => $data->status,
            'published_at'          => $publishedAt,
            'read_time_minutes'     => $readTime,
            'is_featured'           => $data->is_featured,
            'main_productable_id'   => null,
            'main_productable_type' => null,
            'thumbnail_url'         => $coverImageUrl,
            'meta_title'            => $data->meta_title,
            'meta_description'      => $data->meta_description,
            'meta_keywords'         => $data->meta_keywords,
        ];
        if ($data->main_productable) {
            $postData['main_productable_id']   = $data->main_productable['id'];
            $postData['main_productable_type'] = ProductableEnum::from($data->main_productable['type'])
                ->getModelClass();
        }
        $post->update($postData);
        foreach ($media as $key => $mediaId) {
            $post->syncMedia($mediaId, $key);
        }
        $post->categories()->sync($data->category_ids);

        $post->syncRelatedProductables($data->related_productables);
        $post->refresh();

        $this->cache->invalidate(CacheTag::Search);
        StorefrontCacheInvalidated::dispatch(StorefrontTag::BlogPosts, StorefrontTag::HomeBlocks);

        return $post;
    }

    private function calculateReadTime(string $body): int
    {
        // str_word_count() only recognises ASCII words, so Persian text would count as
        // zero, and entities are decoded first so an `&nbsp;` separates words instead
        // of gluing them into one token or counting as a word itself.
        $text      = html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $words     = preg_split('/[\s\p{Z}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $wordCount = count($words);

        return max(1, (int) ceil($wordCount / 200)); // 200 wpm average
    }
}
