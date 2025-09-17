<?php

declare(strict_types=1);

namespace App\Actions\Admin\Blog\Post;

use App\Data\Admin\Blog\Post\BlogPostUpdateData;
use App\Enums\ProductableEnum;
use App\Models\Blog\BlogPost;
use Illuminate\Support\Str;

final readonly class UpdateBlogPostAction
{
    public function handle(BlogPost $post, BlogPostUpdateData $data): BlogPost
    {
        $slug = $data->slug ?? Str::slug($data->title);
        $readTime = $this->calculateReadTime($data->body);
        $postData = [
            'title'                 => $data->title,
            'slug'                  => $slug,
            'body'                  => $data->body,
            'excerpt'               => $data->excerpt,
            'author_id'             => $data->author_id ?? $post->author_id,
            'status'                => $data->status,
            'published_at'          => $data->published_at,
            'read_time_minutes'     => $readTime,
            'is_featured'           => $data->is_featured,
            'main_productable_id'   => null,
            'main_productable_type' => null,
        ];
        if ($data->main_productable) {
            $postData['main_productable_id'] = $data->main_productable['id'];
            $postData['main_productable_type'] = ProductableEnum::from($data->main_productable['type'])
                ->getModelClass();
        }
        $post->update($postData);

        $post->syncMedia($data->main_media, 'main');

        $post->categories()->sync($data->category_ids);

        $post->syncRelatedProductables($data->related_productables);
        $post->refresh();
        return $post;
    }

    private function calculateReadTime(string $body): int
    {
        $wordCount = str_word_count(strip_tags($body));
        return max(1, (int) ceil($wordCount / 200)); // 200 wpm average
    }
}
