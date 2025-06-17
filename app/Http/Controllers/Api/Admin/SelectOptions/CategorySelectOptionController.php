<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\SelectOptions;

use App\Data\Category\CategorySelectOptionData;
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
     *
     * @responseFile 200 responses/select-options/category.json
     */
    public function __invoke()
    {
        $query = request()->string('q', '');

        $categories = \App\Models\Category::query()
            ->withMediaAndVariants(['icon'])
            ->when($query, function ($category) use ($query) {
                $category->where(function ($category) use ($query) {
                    $category
                        ->where('name', 'like', '%'.$query.'%')
                        ->orWhere('slug', 'like', '%'.$query.'%');
                });
            })
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'slug', 'icon_url']);

        return response()->success(
            CategorySelectOptionData::collect($categories)
        );
    }
}
