<?php

declare(strict_types=1);

namespace App\Actions\Admin\Blog\Post;

use App\Data\Admin\Blog\Post\BlogPostCreateData;
use App\Enums\MediaTagEnum;
use App\Enums\Product\ProductableEnum;
use App\Models\Blog\BlogPost;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Plank\Mediable\Media;

final readonly class CreateBlogPostAction
{
    public function handle(BlogPostCreateData $data, ?Staff $staff = null): BlogPost
    {
        return DB::transaction(function () use ($data, $staff): BlogPost {
            // retry Slug to get unique slug if exists
            $slug = $data->slug ?? Str::slug($data->title);
            while (BlogPost::where('slug', $slug)->exists()) {
                $slug = Str::slug($data->title.'-'.Str::random(5));
            }
            $readTime      = $this->calculateReadTime($data->body);
            $media         = $data->media;
            $coverImageUrl = null;
            if ($cover = data_get($media, MediaTagEnum::COVER->value.'.0')) {
                $coverImageUrl = Media::find($cover)?->getUrl();
            }
            $postData = [
                'title'             => $data->title,
                'slug'              => $slug,
                'body'              => $data->body,
                'excerpt'           => $data->excerpt,
                'author_id'         => $data->author_id ?? $staff?->id,
                'status'            => $data->status,
                'published_at'      => $data->published_at,
                'read_time_minutes' => $readTime,
                'is_featured'       => $data->is_featured ?? false,
                'thumbnail_url'     => $coverImageUrl,
                'meta_title'        => $data->meta_title,
                'meta_description'  => $data->meta_description,
                'meta_keywords'     => $data->meta_keywords,
            ];

            if ($data->main_productable) {
                $postData['main_productable_id']   = $data->main_productable['id'];
                $postData['main_productable_type'] = ProductableEnum::from($data->main_productable['type'])
                    ->getModelClass();
            }

            $post = BlogPost::create($postData);

            foreach ($media as $key => $mediaId) {
                $post->attachMedia($mediaId, $key);
            }

            if (! empty($data->category_ids)) {
                $post->categories()->sync($data->category_ids);
            }

            if (! empty($data->related_productables)) {
                $post->syncRelatedProductables($data->related_productables);
            }
            $post->refresh();

            return $post;
        });

    }

    private function calculateReadTime(string $body): int
    {
        $wordCount = str_word_count(strip_tags($body));

        return max(1, (int) ceil($wordCount / 200)); // 200 wpm average
    }
}
