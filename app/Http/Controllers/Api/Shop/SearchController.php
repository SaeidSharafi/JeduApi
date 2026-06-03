<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop;

use App\Data\Shop\Blog\BlogPostCardData;
use App\Data\Shop\Product\ProductCardData;
use App\Data\Shop\Search\SearchData;
use App\Http\Controllers\Controller;
use App\Services\GlobalSearchService;
use App\Services\ProductPriceService;

/**
 * @group Shop - Search
 *
 * APIs for searching products and blog posts
 */
final class SearchController extends Controller
{
    /**
     * Global Search
     *
     * Perform a global search across products and blog posts with faceted filtering.
     *
     * @responseFile 200 resources/responses/shop/search.json
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function __invoke(SearchData $searchData, GlobalSearchService $service, ProductPriceService $priceService)
    {
        // Pass SearchData DTO directly to service - no intermediate transformation needed!
        $results = $service->search($searchData);

        $data = $results->through(function ($item) use ($priceService) {
            if ($item instanceof \App\Models\Product) {
                $priceData = $priceService->getPriceDataForProduct($item);

                return ProductCardData::fromModel($item, $priceData, withFullPriceData: false)
                    ->additional(['type' => 'product']);
            }
            if ($item instanceof \App\Models\Blog\BlogPost) {
                return BlogPostCardData::from($item)
                    ->additional(['type' => 'blog_post']);
            }

            // @codeCoverageIgnoreStart
            return null; // Should never happen - only Product or BlogPost in results
            // @codeCoverageIgnoreEnd
        });

        return response()->success($data);
    }
}
