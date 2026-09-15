<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\SelectOptions;

use App\Data\Admin\SelectOptions\CategorySelectOptionData;
use App\Http\Controllers\Controller;

/**
 * @group Admin - Select Options
 *
 * retrieve a list of categories for select options
 *
 * @authenticated
 */
final class CategorySelectOptionController extends Controller
{
    /**
     * Categories list
     *
     * @queryParam  q string The search query for filtering categories (match name and slug). Example: "electronics"
     * @queryParam  page integer The page number for pagination. Example: 2
     * @queryParam  per_page integer The number of results per page. Default is 15. Example: 10
     *
     * @responseFile 200 resources/responses/admin/select-options/category.json
     */
    public function __invoke(): \App\Contracts\ApiResponseInterface
    {
        $query   = request()->string('q', '');
        $perPage = request()->integer('per_page', config('app.page_size')) ?: (int) config('app.page_size');

        $categories = \App\Models\Category::query()
            ->withMediaAndVariants(['icon'])
            ->when($query, function ($category) use ($query): void {
                $category->where(function ($category) use ($query): void {
                    $category
                        ->whereLike('name', '%'.$query.'%')
                        ->orWhereLike('slug', '%'.$query.'%');
                });
            })
            ->orderBy('name')
            ->paginate($perPage, ['id', 'name', 'slug', 'icon_url'])
            ->withQueryString();

        return apiResponse()->success(
            CategorySelectOptionData::collect($categories)
        );
    }
}
