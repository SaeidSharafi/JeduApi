<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Blog;

use App\Data\Shop\Blog\BlogPostCardData;
use App\Data\Shop\Blog\BlogPostDetailData;
use App\Data\Shop\Blog\BlogPostListRequestData;
use App\Enums\Content\PublicationStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Blog\BlogPost;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * @group Shop - Blog - Posts
 *
 * APIs for retrieving blog posts in the shop.
 */
final class BlogPostController extends Controller
{
    /**
     * Blog Post List
     *
     * Retrieve a paginated list of published blog posts with optional filtering and sorting.
     *
     * @responseFile storage/responses/shop/blog/post/index.json
     */
    public function index(BlogPostListRequestData $requestData)
    {
        $posts = BlogPost::query()
            ->where('status', PublicationStatusEnum::PUBLISHED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->when($requestData->is_featured !== null, function ($q) use ($requestData) {
                $q->where('is_featured', $requestData->is_featured);
            })
            ->when($requestData->category_slug, function ($q) use ($requestData) {
                $q->whereHas('categories', function ($q2) use ($requestData) {
                    $q2->where('slug', $requestData->category_slug);
                });
            })
            ->orderBy($requestData->sortBy, $requestData->sortOrder)
            ->with(['author', 'categories'])
            ->paginate(
                perPage: $requestData->per_page,
                page: $requestData->page
            )
            ->withQueryString();


        $data = BlogPostCardData::collect($posts);

        return response()->success($data);
    }

    /**
     * Blog Post Detail
     *
     * Retrieve detailed information about a specific blog post by its slug.
     *
     * @responseFile 200 storage/responses/shop/blog/post/show.json
     * @responseFile 404 storage/responses/404.json
     */
    public function show(string $slug)
    {
        $post = BlogPost::query()
            ->where('slug', $slug)
            ->where('status', PublicationStatusEnum::PUBLISHED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->with(['author', 'categories'])
            ->withProductableMedia()
            ->firstOrFail();

        return response()->success(BlogPostDetailData::fromModel($post));
    }
}
