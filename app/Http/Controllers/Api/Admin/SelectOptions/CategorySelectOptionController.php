<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\SelectOptions;

use App\Data\Admin\SelectOptions\CategorySelectOptionData;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;

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
     * @responseFile 200 resources/responses/admin/select-options/category.json
     */
    public function __invoke()
    {
        $query = request()->string('q', '');
        $limit = request()->integer('limit', 10);

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
            ->when($limit, fn (Builder $q): Builder => $q->limit($limit))
            ->get(['id', 'name', 'slug', 'icon_url']);

        return response()->success(
            CategorySelectOptionData::collect($categories)
        );
    }
}
