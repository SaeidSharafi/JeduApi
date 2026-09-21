<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Product;

use App\Contracts\Cache\CacheStore;
use App\Data\Shop\Product\ProductCardData;
use App\Enums\Product\ProductableEnum;
use App\Enums\System\CacheKey;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Query\ProductQueryService;
use App\Services\ProductPriceService;

/**
 * @group Shop - Products - Categories
 *
 * APIs for retrieving good-for-start courses in a specific category.
 */
final class GoodForStartCoursesController extends Controller
{
    public function __construct(private readonly CacheStore $cache) {}

    /**
     * Good For Start Courses
     *
     * Retrieve a list of courses that are marked as "good for start" within a specific category.
     *
     * @queryParam limit int The maximum number of courses to return. Default is 10. Example: 5
     *
     * @responseFile 200 resources/responses/shop/products/categories/good-for-start.json
     */
    public function __invoke(Category $category, ProductPriceService $priceService): \App\Contracts\ApiResponseInterface
    {
        $limit = request()->integer('limit', 10);

        $courses = $this->cache->remember(
            CacheKey::GoodForStart,
            ['slug' => $category->slug, 'limit' => $limit],
            function () use ($category, $priceService, $limit) {
                return ProductQueryService::make()
                    ->ofType(ProductableEnum::COURSE)
                    ->availableProducts()
                    ->goodForStart([$category->slug])
                    ->forListing()
                    ->limit($limit)
                    ->get()
                    ->map(function (Product $product) use ($priceService): ProductCardData {
                        $priceData = $priceService->getPriceDataForProduct($product);

                        return ProductCardData::fromModel($product, $priceData);
                    });
            });

        return apiResponse()->success($courses);
    }
}
