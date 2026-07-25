<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Blog;

use App\Actions\Admin\Blog\Category\CreateBlogCategoryAction;
use App\Actions\Admin\Blog\Category\DeleteBlogCategoryAction;
use App\Actions\Admin\Blog\Category\UpdateBlogCategoryAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Blog\Category\BlogCategoryCreateData;
use App\Data\Admin\Blog\Category\BlogCategoryData;
use App\Data\Admin\Blog\Category\BlogCategoryUpdateData;
use App\Http\Controllers\Controller;
use App\Models\Blog\BlogCategory;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @group Admin - Blog - Categories
 *
 * APIs for managing blog categories
 */
final class BlogCategoryController extends Controller
{
    /**
     * List Blog Categories
     *
     * Display a listing of blog categories.
     *
     * @queryParam filter[name] string Filter by category name. Example: Technology
     * @queryParam filter[slug] string Filter by category slug. Example: technology
     * @queryParam sort string Sort by fields. Allowed values: name, created_at, updated_at. Prefix with '-' for descending order. Example: -created_at
     * @queryParam per_page integer Number of items per page. Example: 15
     * @queryParam page integer Page number. Example: 1
     *
     * @responseFile 200 resources/responses/admin/blog/category/index.json
     * @responseFile 403 resources/responses/403.json
     */
    public function index(): ApiResponseInterface
    {
        Gate::authorize('viewAny', BlogCategory::class);
        $categories = QueryBuilder::for(BlogCategory::class)
            ->allowedFilters(['name', 'slug'])
            ->allowedSorts(['name', 'created_at', 'updated_at'])
            ->defaultSort('-created_at')
            ->withCount('posts')
            ->with('media')
            ->paginate(request()->integer('per_page', config('app.page_size')))
            ->withQueryString();

        return apiResponse()->success(BlogCategoryData::collect($categories));
    }

    /**
     * Get Blog Category
     *
     * Display the specified blog category.
     *
     * @responseFile 200 resources/responses/admin/blog/category/show.json
     * @responseFile 403 resources/responses/403.json
     * @responseFile 404 resources/responses/404.json
     */
    public function show(BlogCategory $category): ApiResponseInterface
    {
        Gate::authorize('view', $category);
        $category->load('media');

        return apiResponse()->success(BlogCategoryData::fromModel($category));
    }

    /**
     * Create Blog Category
     *
     * Store a newly created blog category in storage.
     *
     * @responseFile 201 resources/responses/admin/blog/category/show.json
     * @responseFile 403 resources/responses/403.json
     * @responseFile 422 resources/responses/422.json
     */
    public function store(BlogCategoryCreateData $data, CreateBlogCategoryAction $action): ApiResponseInterface
    {
        Gate::authorize('create', BlogCategory::class);
        $category = $action->handle($data);
        $category->load('media');

        return apiResponse()->created(BlogCategoryData::fromModel($category), model: BlogCategory::class);
    }

    /**
     * Update Blog Category
     *
     * Update the specified blog category in storage.
     *
     * @responseFile 200 resources/responses/admin/blog/category/show.json
     * @responseFile 403 resources/responses/403.json
     * @responseFile 404 resources/responses/404.json
     * @responseFile 422 resources/responses/422.json
     */
    public function update(BlogCategory $category, BlogCategoryUpdateData $data, UpdateBlogCategoryAction $action): ApiResponseInterface
    {
        Gate::authorize('update', $category);
        $category = $action->handle($category, $data);
        $category->load('media');

        return apiResponse()->updated(BlogCategoryData::fromModel($category), model: $category);
    }

    /**
     * Delete Blog Category
     *
     * Remove the specified blog category from storage.
     *
     * @response 204
     *
     * @responseFile 403 resources/responses/403.json
     * @responseFile 404 resources/responses/404.json
     */
    public function destroy(BlogCategory $category, DeleteBlogCategoryAction $action): ApiResponseInterface
    {
        Gate::authorize('delete', $category);
        $action->handle($category);

        return apiResponse()->success();
    }
}
