<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Blog;

use App\Actions\Admin\Blog\Post\CreateBlogPostAction;
use App\Actions\Admin\Blog\Post\DeleteBlogPostAction;
use App\Actions\Admin\Blog\Post\UpdateBlogPostAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Blog\Post\BlogPostCreateData;
use App\Data\Admin\Blog\Post\BlogPostData;
use App\Data\Admin\Blog\Post\BlogPostListItemData;
use App\Data\Admin\Blog\Post\BlogPostUpdateData;
use App\Http\Controllers\Controller;
use App\Models\Blog\BlogPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @group Admin - Blog - Posts
 *
 * APIs for managing blog posts
 */
final class BlogPostController extends Controller
{
    /**
     * List Blog Posts
     *
     * Display a listing of blog posts with their related data.
     *
     * @queryParam filter[title] string Filter by title. Example: filter[title]=Laravel
     * @queryParam filter[slug] string Filter by slug. Example: filter[slug]=laravel-introduction
     * @queryParam filter[is_published] boolean Filter by published status. Example: filter[is_published]=1
     * @queryParam filter[author_id] integer Filter by author ID. Example: filter[author_id]=3
     * @queryParam filter[main_productable_type] string Filter by main productable type. Example: filter[main_productable_type]=course
     * @queryParam filter[main_productable_id] integer Filter by main productable ID. Example: filter[main_productable_id]=5
     *             note: this filter is connected to mainProductable_type filter, without it, this filter will not work
     *
     * @responseFile 200 resources/responses/admin/blog/post/index.json
     * @responseFile 403 resources/responses/403.json
     */
    public function index(): ApiResponseInterface
    {
        Gate::authorize('viewAny', BlogPost::class);
        $posts = QueryBuilder::for(BlogPost::class)
            ->allowedFilters([
                'title',
                'slug',
                AllowedFilter::exact('status'),
                AllowedFilter::exact('author_id'),
                AllowedFilter::exact('main_productable_type'),
                AllowedFilter::exact('main_productable_id'),
            ])
            ->allowedSorts(['title', 'published_at', 'created_at', 'updated_at'])
            ->defaultSort('-created_at')
            ->with(['courses', 'seminars', 'digitalAssets', 'media', 'mainProductable', 'categories', 'author'])
            ->paginate(request()->integer('per_page', 15))
            ->withQueryString();

        return apiResponse()->success(BlogPostListItemData::collect($posts));
    }

    /**
     * Show Blog Post
     *
     * Display the specified blog post along with its related data.
     *
     * @responseFile 200 resources/responses/admin/blog/post/show.json
     * @responseFile 403 resources/responses/403.json
     * @responseFile 404 resources/responses/404.json
     */
    public function show(BlogPost $post): ApiResponseInterface
    {
        Gate::authorize('view', $post);
        $post
            ->loadRelatedproductables()
            ->load('author', 'media', 'mainProductable');

        return apiResponse()->success(BlogPostData::from($post));
    }

    /**
     * Create Blog Post
     *
     * Store a newly created blog post in storage.
     *
     * @responseFile 201 resources/responses/admin/blog/post/show.json
     * @responseFile 403 resources/responses/403.json
     * @responseFile 422 resources/responses/422.json
     */
    public function store(BlogPostCreateData $data, CreateBlogPostAction $action): ApiResponseInterface
    {
        Gate::authorize('create', BlogPost::class);
        $post = $action->handle($data, staff: auth('staff')->user());
        $post->loadRelatedproductables()
            ->load('author', 'media', 'mainProductable');

        return apiResponse()->created(BlogPostData::from($post), model: BlogPost::class);
    }

    /**
     * Update Blog Post
     *
     * Update the specified blog post in storage.
     *
     * @responseFile 200 resources/responses/admin/blog/post/show.json
     * @responseFile 403 resources/responses/403.json
     * @responseFile 404 resources/responses/404.json
     * @responseFile 422 resources/responses/422.json
     */
    public function update(BlogPost $post, BlogPostUpdateData $data, UpdateBlogPostAction $action): ApiResponseInterface
    {
        Gate::authorize('update', $post);
        $post = $action->handle($post, $data);
        $post->loadRelatedproductables()
            ->load('author', 'media', 'mainProductable');

        return apiResponse()->updated(BlogPostData::from($post), model: $post);
    }

    /**
     * Delete Blog Post
     *
     * Remove the specified blog post from storage.
     *
     * @response 204
     *
     * @responseFile 403 resources/responses/403.json
     * @responseFile 404 resources/responses/404.json
     */
    public function destroy(BlogPost $post, DeleteBlogPostAction $action): JsonResponse
    {
        Gate::authorize('delete', $post);
        $action->handle($post);

        return apiResponse()->noContentJson();
    }
}
