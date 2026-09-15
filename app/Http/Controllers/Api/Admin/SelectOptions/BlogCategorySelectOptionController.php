<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\SelectOptions;

use App\Data\Admin\SelectOptions\BlogCategorySelectOptionData;
use App\Http\Controllers\Controller;
use App\Models\Blog\BlogCategory;

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
     * @queryParam  page integer The page number for pagination. Example: 2
     * @queryParam  per_page integer The number of results per page. Default is 15. Example: 10
     *
     * @responseFile 200 resources/responses/admin/select-options/blog-category.json
     */
    public function __invoke(): \App\Contracts\ApiResponseInterface
    {
        $query   = request()->string('q', '');
        $perPage = request()->integer('per_page', config('app.page_size')) ?: (int) config('app.page_size');

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
            ->paginate($perPage, ['id', 'name', 'slug', 'icon'])
            ->withQueryString();

        return apiResponse()->success(
            BlogCategorySelectOptionData::collect($categories)
        );
    }
}
