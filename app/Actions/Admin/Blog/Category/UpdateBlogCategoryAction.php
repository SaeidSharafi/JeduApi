<?php

declare(strict_types=1);

namespace App\Actions\Admin\Blog\Category;

use App\Data\Admin\Blog\Category\BlogCategoryUpdateData;
use App\Models\Blog\BlogCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Plank\Mediable\Media;

final readonly class UpdateBlogCategoryAction
{
    public function handle(BlogCategory $category, BlogCategoryUpdateData $data): BlogCategory
    {
        return DB::transaction(function () use ($data, $category): BlogCategory {
            $slug = $data->slug ?? Str::slug($data->name);
            $icon = null;
            if ($data->icon) {
                $icon = Media::find($data->icon);
            }
            $category->update([
                'name'             => $data->name,
                'slug'             => $slug,
                'description'      => $data->description,
                'parent_id'        => $data->parent_id,
                'icon'             => $icon?->getUrl(),
                'meta_title'       => $data->meta_title,
                'meta_description' => $data->meta_description,
                'meta_keywords'    => $data->meta_keywords,
            ]);
            $category->syncMedia($icon, 'icon');

            return $category->fresh();
        });

    }
}
