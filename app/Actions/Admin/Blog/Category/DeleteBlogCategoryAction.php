<?php

declare(strict_types=1);

namespace App\Actions\Admin\Blog\Category;

use App\Models\Blog\BlogCategory;

final readonly class DeleteBlogCategoryAction
{
    public function handle(BlogCategory $category): void
    {
        $category->media()->delete();
        $category->delete();
    }
}
