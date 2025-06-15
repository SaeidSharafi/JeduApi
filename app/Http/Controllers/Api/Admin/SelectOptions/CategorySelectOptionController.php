<?php

namespace App\Http\Controllers\Api\Admin\SelectOptions;

use App\Data\Category\CategorySelectOptionData;
use App\Http\Controllers\Controller;

class CategorySelectOptionController extends Controller
{
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
