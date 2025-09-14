<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Category;

use App\Actions\Admin\Category\CreateCategoryAction;
use App\Actions\Admin\Category\DeleteCategoryAction;
use App\Actions\Admin\Category\UpdateCategoryAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Category\CategorizableListItemData;
use App\Data\Admin\Category\CategoryListItemData;
use App\Data\Admin\Category\CreateCategoryData;
use App\Data\Admin\Category\ShowCategoryData;
use App\Exceptions\ModelHasRelationshipDataException;
use App\Filters\FiltersMultipleValues;
use App\Http\Controllers\Controller;
use App\Models\Categorizable;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @group Admin - Category Managment
 *
 * @authenticated
 */
final class CategoryItemsController extends Controller
{
    /**
     *
     * Display a listing of the items in the specified category.
     *
     * @queryParam filter[categorizable_type] array Filter by categorizable type. Example: product,course
     *             or filter[categorizable_type][]=product&filter[categorizable_type][]=course
     *
     * @queryParam filter[good_for_start] boolean Filter by good_for_start flag. Example: true
     *
     * @queryParam page integer Page number for pagination. Example: 2
     * @queryParam per_page integer Number of results per page. Example: 15
     *
     * @responseFile 200 responses/category/index.json
     */
    public function __invoke(Category $category): ApiResponseInterface
    {
        Gate::authorize('view', $category);

        $items = QueryBuilder::for(Categorizable::class)
            ->allowedFilters([
                AllowedFilter::custom('categorizable_type', new FiltersMultipleValues()),
                AllowedFilter::exact('good_for_start'),
            ])
            ->where('category_id', $category->id)

            ->with('categorizable')
            ->paginate(request()->integer('per_page', 15))
            ->withQueryString();
        return response()->success(CategorizableListItemData::collect($items));
    }
}
