<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\SelectOptions;

use App\Data\Admin\SelectOptions\BlogCategorySelectOptionData;
use App\Http\Controllers\Controller;
use App\Models\Blog\BlogCategory;
use Illuminate\Database\Eloquent\Builder;

/**
 * @group Admin - Select Options
 *
 * retrieve a list of blog categories for select options
 *
 * @authenticated
 */
final class BlogCategorySelectOptionController extends Controller
{
    /**
     * Blog Categories list
     *
     * @queryParam  q string The search query for filtering blog categories (match name and slug). Example: "electronics"
     *
     * @responseFile 200 resources/responses/admin/select-options/blog-category.json
     */
    public function __invoke(): \App\Contracts\ApiResponseInterface
    {
        $query = request()->string('q', '');
        $limit = request()->integer('limit', 10);

        $categories = BlogCategory::query()
            ->withMediaAndVariants(['icon'])
            ->when($query, function ($category) use ($query): void {
                $category->where(function ($category) use ($query): void {
                    $category
                        ->whereLike('name', '%'.$query.'%')
                        ->orWhereLike('slug', '%'.$query.'%');
                });
            })
            ->orderBy('name')
            ->when($limit, fn (Builder $q): Builder => $q->limit($limit))
            ->get(['id', 'name', 'slug', 'icon']);

        return apiResponse()->success(
            BlogCategorySelectOptionData::collect($categories)
        );
    }
}
