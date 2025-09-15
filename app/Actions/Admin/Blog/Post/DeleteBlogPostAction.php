<?php

declare(strict_types=1);

namespace App\Actions\Admin\Blog\Post;

use App\Models\Blog\BlogPost;

final readonly class DeleteBlogPostAction
{
    public function handle(BlogPost $post): void
    {
        $post->media()->delete();
        $post->delete();
    }
}
