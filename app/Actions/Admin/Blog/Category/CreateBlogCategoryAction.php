<?php

declare(strict_types=1);

namespace App\Actions\Admin\Blog\Category;

use App\Data\Admin\Blog\Category\BlogCategoryCreateData;
use App\Models\Blog\BlogCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Plank\Mediable\Media;

final readonly class CreateBlogCategoryAction
{
    public function handle(BlogCategoryCreateData $data): BlogCategory
    {
        return DB::transaction(function () use ($data): BlogCategory {
            $slug = $data->slug ?? Str::slug($data->name);
            $icon = null;
            if ($data->icon) {
                $icon = Media::find($data->icon);
            }

            $category = BlogCategory::create([
                'name'        => $data->name,
                'slug'        => $slug,
                'description' => $data->description,
                'parent_id'   => $data->parent_id,
                'icon'        => $icon?->getUrl(),
            ]);
            if ($data->icon) {
                $category->syncMedia($icon, 'icon');
            }

            return $category->fresh();
        });

    }
}
