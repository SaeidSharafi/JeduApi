<?php

declare(strict_types=1);

namespace App\Actions\Admin\Blog\Post;

use App\Contracts\Cache\CacheStore;
use App\Enums\System\CacheTag;
use App\Models\Blog\BlogPost;

final readonly class DeleteBlogPostAction
{
    public function __construct(private CacheStore $cache) {}

    public function handle(BlogPost $post): void
    {
        $post->media()->delete();
        $post->delete();

        $this->cache->invalidate(CacheTag::Search);
    }
}
