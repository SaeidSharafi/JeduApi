<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Category\CreateCategoryAction;
use App\Actions\Category\DeleteCategoryAction;
use App\Actions\Category\UpdateCategoryAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Category\CategoryListItemData;
use App\Data\Category\CreateCategoryData;
use App\Data\Category\ShowCategoryData;
use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @group Admin - Category Managment
 *
 * @authenticated
 */
final class CategoryController extends Controller
{
    /**
     * return a listing of the categories.
     *
     * @queryParam filter[slug] string Filter by category slug. Example: electronics
     * @queryParam filter[name] string Filter by category name. Example: Electronics
     * @queryParam filter[status] string Filter by category status. Example: draft
     * @queryParam sort string Sort by a field. Allowed values: slug, name, status. Prefix with '-' for descending order (e.g., -name for descending by name). Example: name
     * @queryParam page integer Page number for pagination. Example: 2
     * @queryParam per_page integer Number of results per page. Example: 15
     *
     * @responseFile 200 responses/category/index.json
     */
    public function index(): ApiResponseInterface
    {
        Gate::authorize('view-any', Category::class);
        $categories = QueryBuilder::for(Category::class)
            ->allowedFilters(['name', 'slug', 'status'])
            ->allowedSorts(['name', 'slug', 'status'])
            ->allowedIncludes(['createdBy'])
            ->paginate()
            ->withQueryString();

        return response()->success(CategoryListItemData::collect($categories));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @responseFile 201 responses/201.json
     * @responseFile 422 responses/422.json
     */
    public function store(CreateCategoryData $data, CreateCategoryAction $action): ApiResponseInterface
    {
        Gate::authorize('create', Category::class);
        $action->handle($data);

        return response()->created(
            __('catalog.category.message.crated'),
        );
    }

    /**
     * return the specified category.
     *
     * @responseFile 200 responses/category/show.json
     * @responseFile 404 responses/404.json
     */
    public function show(Category $category): ApiResponseInterface
    {
        Gate::authorize('view', $category);
        $category->loadMediaWithVariantsMatchAll();

        return response()->success(
            ShowCategoryData::from($category)
        );
    }

    /**
     * Update the specified category in database.
     *
     * @responseFile 200 responses/category/show.json
     * @responseFile 422 responses/422.json
     * @responseFile 404 responses/404.json
     */
    public function update(CreateCategoryData $data, Category $category, UpdateCategoryAction $action): ApiResponseInterface
    {
        Gate::authorize('update', $category);
        $action->handle($data, $category);
        $category
            ->refresh()
            ->loadMediaWithVariantsMatchAll();
        return response()->success(
            ShowCategoryData::from($category),
            __('catalog.category.message.updated')
        );
    }

    /**
     * Remove the specified category from storage.
     *
     * @responseFile 404 responses/404.json
     */
    public function destroy(Category $category, DeleteCategoryAction $action): JsonResponse
    {
        Gate::authorize('delete', $category);
        $action->handle($category);

        return response()->noContentJson();
    }
}
