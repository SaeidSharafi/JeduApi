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
     * @queryParam q string required The search query. Example: "laptop"
     * @queryParam per_page int The number of results to return per page. Default is 15, max 100. Example: 10
     * @queryParam result_types string[] Filter by result type. Options: "product", "blog_post". Returns both if not specified. Example: ["product"]
     * @queryParam productable_type string Filter by product type (e.g., "course", "seminar", "digital_asset"). Example: course
     * @queryParam has_discount boolean Filter products with active discounts. Example: true
     * @queryParam category_ids int[] Filter by category IDs. Example: [1,2,3]
     * @queryParam price_min int Minimum price filter (in smallest currency unit). Example: 100000
     * @queryParam price_max int Maximum price filter (in smallest currency unit). Example: 500000
     * @queryParam level string Filter courses by difficulty level (e.g., "beginner", "intermediate", "advanced"). Example: beginner
     * @queryParam fulfillment_types string[] Filter by delivery/fulfillment types. Example: ["digital","physical"]
     *
     * @responseFile 200 responses/shop/search.json
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
