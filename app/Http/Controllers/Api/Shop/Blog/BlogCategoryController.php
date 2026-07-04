<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Blog;

use App\Data\Shop\Blog\BlogCategoryCardData;
use App\Data\Shop\Blog\BlogCategoryDetailData;
use App\Data\Shop\Blog\BlogPostCardData;
use App\Data\Shop\Blog\BlogPostListRequestData;
use App\Enums\Content\PublicationStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Blog\BlogCategory;
use App\Models\Blog\BlogPost;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * @group Shop - Blog - Categories
 *
 * APIs for retrieving blog categories in the shop.
 */
final class BlogCategoryController extends Controller
{
    /**
     * Blog Category List
     *
     * Retrieve a list of all blog categories with their post counts.
     *
     * @responseFile resources/responses/shop/blog/category/index.json
     */
    public function index()
    {
        $categories = BlogCategory::query()
            ->withCount(['posts' => function ($query): void {
                $query->where('status', PublicationStatusEnum::PUBLISHED)
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', now());
            }])
            ->orderBy('name')
            ->get();

        $data = $categories->map(fn (BlogCategory $category): BlogCategoryCardData => BlogCategoryCardData::fromModel($category));

        return apiResponse()->success($data);
    }

    /**
     * Blog Category Detail
     *
     * Retrieve detailed information about a specific blog category by its slug.
     *
     * @responseFile 200 resources/responses/shop/blog/category/show.json
     * @responseFile 404 resources/responses/404.json
     */
    public function show(string $slug)
    {
        $category = BlogCategory::query()
            ->where('slug', $slug)
            ->withCount(['posts' => function ($query): void {
                $query->where('status', PublicationStatusEnum::PUBLISHED)
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', now());
            }])
            ->firstOrFail();

        return apiResponse()->success(BlogCategoryDetailData::fromModel($category));
    }

    /**
     * Blog Category Posts
     *
     * Retrieve a paginated list of published blog posts for a specific category.
     *
     * @responseFile resources/responses/shop/blog/post/index.json
     */
    public function posts(string $slug, BlogPostListRequestData $requestData)
    {
        $category = BlogCategory::query()
            ->where('slug', $slug)
            ->firstOrFail();

        $query = BlogPost::query()
            ->where('status', PublicationStatusEnum::PUBLISHED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->whereHas('categories', function ($q) use ($category): void {
                $q->where('blog_categories.id', $category->id);
            })
            ->with(['author', 'categories']);

        if ($requestData->is_featured !== null) {
            $query->where('is_featured', $requestData->is_featured);
        }

        $query->orderBy($requestData->sortBy, $requestData->sortOrder);

        /** @var LengthAwarePaginator<BlogPost> $posts */
        $posts = $query->paginate(
            perPage: $requestData->per_page,
            page: $requestData->page
        );

        $data = BlogPostCardData::collect($posts);

        return apiResponse()->success($data);
    }
}
