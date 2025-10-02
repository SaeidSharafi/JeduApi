<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Product;

use App\Data\Shop\Product\Course\CourseDetailData;
use App\Data\Shop\Product\Course\CourseListRequestData;
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
     * @queryParam search string Optional search term to filter courses by title or description. Example: "programming"
     * @queryParam filter[fulfillment_type] string Optional fulfillment type to filter courses by. Example: "online"
     * @queryParam filter[level] string Optional course level to filter courses by level. Example: "beginner"
     * @queryParam filter[instructor_id] int Optional instructor ID to filter courses by instructor. Example: 5
     * @queryParam sortBy string Optional field to sort by. Default is "created_at". Example: "title"
     * @queryParam sortOrder string Optional sort order, either "asc" or "desc". Default is "desc"
     * @queryParam page int Optional page number for pagination. Default is 1. Example: 1
     * @queryParam per_page int Optional number of items per page. Default is 15. Example: 15
     *
     * @responseFile responses/shop/products/courses/index.json
     */
    public function index(CourseListRequestData $requestData)
    {
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
