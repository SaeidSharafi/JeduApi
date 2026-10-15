<?php

declare(strict_types=1);

namespace App\Actions\Admin\Blog\Category;

use App\Enums\System\StorefrontTag;
use App\Events\StorefrontCacheInvalidated;
use App\Models\Blog\BlogCategory;

final readonly class DeleteBlogCategoryAction
{
    public function handle(BlogCategory $category): void
    {
        $category->media()->delete();
        $category->delete();

        StorefrontCacheInvalidated::dispatch(StorefrontTag::BlogCategories, StorefrontTag::BlogPosts, StorefrontTag::HomeBlocks);
    }
}
