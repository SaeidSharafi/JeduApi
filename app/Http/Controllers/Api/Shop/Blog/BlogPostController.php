<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Blog;

use App\Data\Shop\Blog\BlogPostCardData;
use App\Data\Shop\Blog\BlogPostDetailData;
use App\Data\Shop\Blog\BlogPostListRequestData;
use App\Data\Shop\Product\ProductCardData;
use App\Enums\Content\PublicationStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Blog\BlogPost;
use App\Models\Product;
use App\Query\ProductQueryService;
use App\Services\ProductPriceService;
use Illuminate\Database\Eloquent\Builder;

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
     * @responseFile resources/responses/shop/blog/post/index.json
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
            ->when($requestData->sortBy === 'popularity', function ($q) {
                $q->orderByDesc('average_rating');
            }, function ($q) use ($requestData) {
                $q->orderBy($requestData->sortBy, $requestData->sortOrder);
            })
            ->with(['author', 'categories'])
            ->withMediaAndVariants(['cover'])
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
     * @responseFile 200 resources/responses/shop/blog/post/show.json
     * @responseFile 404 resources/responses/404.json
     */
    public function show(string $slug, ProductPriceService $productPriceService)
    {
        $post = BlogPost::query()
            ->where('slug', $slug)
            ->where('status', PublicationStatusEnum::PUBLISHED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->with(['author', 'categories', 'courses:id', 'seminars:id', 'digitalAssets:id'])
            ->withProductableMedia()
            ->firstOrFail();
        $relatedProductableIds = $post->related_productables->groupBy('pivot.productable_type')
            ->map(fn ($group) => $group->pluck('id')->all())
            ->all();

        $relatedProducts = ProductQueryService::make()
            ->availableProducts()
            ->forListing()
            ->getQuery()
            ->where(function (Builder $query) use ($relatedProductableIds) {
                $query->where(fn (Builder $query) => $query->where('productable_type', 'course')
                    ->whereIn('productable_id', $relatedProductableIds['course'] ?? []))
                    ->orWhere(fn (Builder $query) => $query->where('productable_type', 'seminar')
                        ->whereIn('productable_id', $relatedProductableIds['seminar'] ?? []))
                    ->orWhere(fn (Builder $query) => $query->where('productable_type', 'digital_asset')
                        ->whereIn('productable_id', $relatedProductableIds['digital_asset'] ?? []));
            })
            ->limit(10)
            ->get()
            ->map(function (Product $product) use ($productPriceService) {
                $priceData = $productPriceService->getPriceDataForProduct($product);

                return ProductCardData::fromModel($product, $priceData);
            })
            ->all();

        return response()->success(BlogPostDetailData::fromModel($post, $relatedProducts));
    }
}
