<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Product;

use App\Data\Shop\Product\Course\CourseDetailData;
use App\Data\Shop\Product\Course\ProductListRequestData;
use App\Data\Shop\Product\ProductCardData;
use App\Enums\Product\ProductableEnum;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Query\ProductQueryService;
use App\Services\ProductPriceService;

/**
 * @group Shop - Products - Courses
 *
 * APIs for retrieving courses in the shop.
 */
final class CourseController extends Controller
{
    public function __construct(
        private ProductPriceService $priceService,
    ) {}

    /**
     * Course List
     *
     * Retrieve a paginated list of active product of course type with optional filtering and sorting.
     *
     * @ignoreQueryParam type
     * @responseFile responses/shop/products/courses/index.json
     */
    public function index(ProductListRequestData $requestData)
    {
        $requestData->type = ProductableEnum::COURSE->value;
        $courses = ProductQueryService::make()
            ->availableProducts()
            ->ofType(ProductableEnum::COURSE)
            ->getCourseList($requestData)
            ->through(function (Product $product) {
                $priceData = $this->priceService->getPriceDataForProduct($product);

                return ProductCardData::fromModel($product, $priceData);
            });

        return response()->success($courses);
    }

    /**
     * Course Detail
     *
     * Retrieve detailed information about a specific product of course type by its slug.
     *
     * @responseFile  200 responses/shop/products/courses/show.json
     * @responseFile  404 responses/404.json
     */
    public function show(Product $product)
    {
        // Load the product with all required relations for detail view
        $product = ProductQueryService::make()
            ->ofType(ProductableEnum::COURSE)
            ->availableProducts()
            ->forDetail()
            ->getQuery()
            ->where('id', $product->id)
            ->firstOrFail();

        $priceData = $this->priceService->getPriceDataForProduct($product);

        return response()->success(CourseDetailData::fromModel($product, $priceData));
    }
}
