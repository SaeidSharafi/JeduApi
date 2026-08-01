<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Product;

use App\Contracts\ApiResponseInterface;
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
     * Retrieve a paginated list of active courses with optional filtering and sorting.
     *
     * **Default Behavior**: Only courses currently available for content access are returned
     * unless explicitly overridden by filter parameters.
     *
     * **Availability Filters**:
     * - `filter.is_available_now=true`: Returns only currently available courses (default behavior)
     * - `filter.availability_status`: Filter by temporal state (past/upcoming/ongoing) - overrides is_available_now
     * - `filter.available_from` / `filter.available_to`: Custom availability date range
     *
     * **Registration Filters** (applied within available courses):
     * - `filter.registration_starts_after`: Registration opens on/after this date
     * - `filter.registration_ends_before`: Registration closes on/before this date
     *
     * @ignoreQueryParam type
     *
     * @responseFile resources/responses/shop/products/courses/index.json
     */
    public function index(ProductListRequestData $requestData): ApiResponseInterface
    {
        $requestData->type = ProductableEnum::COURSE->value;
        $courses           = ProductQueryService::make()
            ->ofType(ProductableEnum::COURSE)
            ->getCourseList($requestData)
            ->through(function (Product $product) {
                $priceData = $this->priceService->getPriceDataForProduct($product);

                return ProductCardData::fromModel($product, $priceData);
            });

        return apiResponse()->success($courses);
    }

    /**
     * Course Detail
     *
     * Retrieve detailed information about a specific product of course type by its slug.
     *
     * @responseFile  200 resources/responses/shop/products/courses/show.json
     * @responseFile  404 resources/responses/404.json
     */
    public function show(Product $product): ApiResponseInterface
    {
        // Load the product with all required relations for detail view
        $product = Product::query()
            ->ofType(ProductableEnum::COURSE)
            ->publishedAndVisible()
            ->hasPublishedDeliveryOption()
            ->publishedProductable()
            ->activeTerm()
            ->forDetail()
            ->where('id', $product->id)
            ->firstOrFail();

        $priceData = $this->priceService->getPriceDataForProduct($product);

        return apiResponse()->success(CourseDetailData::fromModel($product, $priceData));
    }
}
