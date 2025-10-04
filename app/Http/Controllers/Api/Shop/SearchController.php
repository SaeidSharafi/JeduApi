<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop;

use App\Data\Shop\Blog\BlogPostCardData;
use App\Data\Shop\Product\ProductCardData;
use App\Http\Controllers\Controller;
use App\Services\GlobalSearchService;
use App\Services\ProductPriceService;
use Illuminate\Http\Request;

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
    public function __invoke(Request $request, GlobalSearchService $service, ProductPriceService $priceService)
    {
        $request->validate([
            'q'                   => 'required|string|max:255',
            'per_page'            => 'sometimes|integer|min:1|max:100',
            'productable_type'    => 'sometimes|string',
            'has_discount'        => 'sometimes|boolean',
            'category_ids'        => 'sometimes|array',
            'category_ids.*'      => 'integer',
            'price_min'           => 'sometimes|integer|min:0',
            'price_max'           => 'sometimes|integer',
            'level'               => 'sometimes|string',
            'fulfillment_types'   => 'sometimes|array',
            'fulfillment_types.*' => 'string',
        ]);

        $query   = $request->input('q');
        $perPage = $request->input('per_page', 15);
        $filters = $request->only([
            'productable_type',
            'has_discount',
            'category_ids',
            'price_min',
            'price_max',
            'level',
            'fulfillment_types',
        ]);

        $results = $service->search($query, $perPage, $filters);

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

            return null;
        });

        return response()->success($data);
    }
}
