<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Product;

use App\Data\Shop\PaginationRequestData;
use App\Enums\Product\ProductableEnum;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Query\CategoryQueryService;

/**
 * @group Shop - Products - Categories
 *
 * APIs for retrieving courses within a specific product category in the shop.
 */
final class CategoryCourseController extends Controller
{
    /**
     * Category Courses
     *
     * Retrieve a paginated list of courses associated with a specific product category.
     *
     * @urlParam category_slug string required The slug of the category. Example: programming
     *
     * @queryParam per_page int Optional number of items per page. Default is 15. Example: 15
     * @queryParam page int Optional page number for pagination. Default is 1. Example: 1
     *
     * @responseFile resources/responses/shop/products/categories/courses.json
     */
    public function __invoke(PaginationRequestData $data, Category $category, CategoryQueryService $service)
    {
        $paginatedCourses = $service->getProductsForCategory($category, ProductableEnum::COURSE, $data->per_page, true);

        return response()->success($paginatedCourses);
    }
}
