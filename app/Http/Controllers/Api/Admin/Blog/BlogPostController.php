<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Blog;

use App\Actions\Admin\Blog\Post\CreateBlogPostAction;
use App\Actions\Admin\Blog\Post\UpdateBlogPostAction;
use App\Actions\Admin\Blog\Post\DeleteBlogPostAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Blog\Post\BlogPostCreateData;
use App\Data\Admin\Blog\Post\BlogPostUpdateData;
use App\Data\Admin\Blog\Post\BlogPostData;
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
     * @queryParam filter[mainProductable_type] string Filter by main productable type. Example: filter[mainProductable_type]=course
     * @queryParam filter[mainProductable_id] integer Filter by main productable ID. Example: filter[mainProductable_id]=5
     *             note: this filter is connected to mainProductable_type filter, without it, this filter will not work
     *
     *
     */
    public function index(): ApiResponseInterface
    {
        Gate::authorize('viewAny', BlogPost::class);
        $posts = QueryBuilder::for(BlogPost::class)
            ->allowedFilters([
                'title',
                'slug',
                AllowedFilter::exact('is_published'),
                AllowedFilter::exact('author_id'),
                AllowedFilter::exact('mainProductable_type'),
                AllowedFilter::exact('mainProductable_id'),
            ])
            ->allowedSorts(['title', 'published_at', 'created_at', 'updated_at'])
            ->defaultSort('-created_at')
            ->with(['courses', 'seminars', 'digitalAssets', 'media', 'mainProductable', 'categories', 'author'])
            ->paginate(request()->integer('per_page', 15))
            ->withQueryString()
        ;
        return response()->success(BlogPostData::collect($posts));
    }

    /**
     * Show Blog Post
     *
     * Display the specified blog post along with its related data.
     */
    public function show(BlogPost $post): ApiResponseInterface
    {
        Gate::authorize('view', $post);
        $post
            ->loadRelatedproducts()
            ->load('author', 'media', 'mainProductable');
        return response()->success(BlogPostData::from($post));
    }

    /**
     * Create Blog Post
     *
     * Store a newly created blog post in storage.
     */
    public function store(BlogPostCreateData $data, CreateBlogPostAction $action): ApiResponseInterface
    {
        Gate::authorize('create', BlogPost::class);
        $post = $action->handle($data);
        $post->loadRelatedproducts()
            ->load('author', 'media', 'mainProductable');
        return response()->created(BlogPostData::from($post), model: BlogPost::class);
    }

    /**
     * Update Blog Post
     *
     * Update the specified blog post in storage.
     */
    public function update(BlogPost $post, BlogPostUpdateData $data, UpdateBlogPostAction $action): ApiResponseInterface
    {
        Gate::authorize('update', $post);
        $post = $action->handle($post, $data);
        $post->loadRelatedproducts()
            ->load('author', 'media', 'mainProductable');
        return response()->updated(BlogPostData::from($post), model: $post);
    }

    /**
     * Delete Blog Post
     *
     * Remove the specified blog post from storage.
     */
    public function destroy(BlogPost $post, DeleteBlogPostAction $action): JsonResponse
    {
        Gate::authorize('delete', $post);
        $action->handle($post);
        return response()->noContentJson();
    }
}
