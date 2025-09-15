<?php

declare(strict_types=1);

namespace App\Actions\Admin\Blog\Post;

use App\Data\Admin\Blog\Post\BlogPostCreateData;
use App\Enums\ProductableEnum;
use App\Models\Blog\BlogPost;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class CreateBlogPostAction
{
    public function handle(BlogPostCreateData $data): BlogPost
    {
        return DB::transaction(function () use ($data): BlogPost {
            $slug = $data->slug ?? Str::slug($data->title);
            $readTime = $this->calculateReadTime($data->body);

            $postData =[
                'title'             => $data->title,
                'slug'              => $slug,
                'body'              => $data->body,
                'excerpt'           => $data->excerpt,
                'author_id'         => $data->author_id,
                'status'            => $data->status,
                'published_at'      => $data->published_at,
                'read_time_minutes' => $readTime,
                'is_featured'       => $data->is_featured,
            ];

            if ($data->main_productable) {
                $postData['main_productable_id'] = $data->main_productable['id'];
                $postData['main_productable_type'] = ProductableEnum::from($data->main_productable['type'])->getModelClass();
            }

            $post = BlogPost::create($postData);

            if ($data->main_media) {
                $post->attachMedia($data->main_media, 'main');
            }

            if (!empty($data->category_ids)) {
                $post->categories()->sync($data->category_ids);
            }

            if (!empty($data->related_productables)) {
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
