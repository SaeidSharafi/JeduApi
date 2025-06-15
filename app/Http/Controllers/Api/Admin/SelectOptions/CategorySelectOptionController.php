<?php

namespace App\Http\Controllers\Api\Admin\SelectOptions;

use App\Data\Category\CategorySelectOptionData;
use App\Http\Controllers\Controller;

/**
 * @group Admin Select Options
 *
 * retrieve a list of categories for select options
 *
 * @authenticated
 */
class CategorySelectOptionController extends Controller
{

    /**
     * @queryParam  q string The search query for filtering categories (match name and slug). Example: "electronics"
     *
     * @responseFile 200 responses/select-options/category.json
     */
    public function __invoke()
    {
        $query = request()->string('q', '');

        $categories = \App\Models\Category::query()
            ->withMediaAndVariants(['icon'])
            ->where('name', 'like', "%{$query}%")
            ->orWhere('slug', 'like', "%{$query}%")
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'slug', 'icon_url']);
        return response()->success(
            CategorySelectOptionData::collect($categories)
        );
    }
}
