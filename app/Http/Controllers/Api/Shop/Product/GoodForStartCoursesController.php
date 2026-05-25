<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Product;

use App\Data\Shop\Product\ProductCardData;
use App\Enums\Product\ProductableEnum;
use App\Enums\System\CacheKeysEnum;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Query\ProductQueryService;
use App\Services\ProductPriceService;
use SmartCache\Facades\SmartCache;

/**
 * @group Shop - Products - Categories
 *
 * APIs for retrieving good-for-start courses in a specific category.
 */
final class GoodForStartCoursesController extends Controller
{
    /**
     * Good For Start Courses
     *
     * Retrieve a list of courses that are marked as "good for start" within a specific category.
     *
     * @queryParam limit int The maximum number of courses to return. Default is 10. Example: 5
     *
     * @responseFile 200 responses/shop/products/categories/good-for-start.json
     */
    public function __invoke(Category $category, ProductPriceService $priceService)
    {

        $courses = SmartCache::remember(
            CacheKeysEnum::GoodForStart->key(['slug' => $category->slug]).'-'.request()->integer('limit', 10),
            CacheKeysEnum::GoodForStart->ttl(),
            function () use ($category, $priceService) {
                return ProductQueryService::make()
                    ->ofType(ProductableEnum::COURSE)
                    ->availableProducts()
                    ->goodForStart([$category->slug])
                    ->forListing()
                    ->limit(request()->integer('limit', 10))
                    ->get()
                    ->map(function (Product $product) use ($priceService) {
                        $priceData = $priceService->getPriceDataForProduct($product);

                        return ProductCardData::fromModel($product, $priceData);
                    });
            });

        return response()->success($courses);
    }
}
