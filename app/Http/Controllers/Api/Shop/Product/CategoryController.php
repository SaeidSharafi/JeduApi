<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Product;

use App\Data\Shop\PaginationRequestData;
use App\Data\Shop\Product\Category\CategoryCardData;
use App\Data\Shop\Product\Category\CategoryDetailData;
use App\Enums\Product\ProductableEnum;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Query\CategoryQueryService;
use App\Query\ProductQueryService;

/**
 * @group Shop - Products - Categories
 *
 * APIs for retrieving product categories in the shop.
 */
final class CategoryController extends Controller
{
    /**
     * Category List
     *
     * Retrieve a list of all product categories with the count of available products in each category.
     *
     * @responseFile resources/responses/shop/products/categories/index.json
     */
    public function index()
    {
        $categories = Category::query()
            ->with('children')
            ->withCount([
                'products' => function ($query) {
                    return ProductQueryService::make()
                        ->setQuery($query)
                        ->availableProducts()
                        ->getQuery();
                },
            ])->get();

        return apiResponse()->success(CategoryCardData::collect($categories));
    }

    /**
     * Category Detail
     *
     * Retrieve detailed information about a specific product category, including paginated lists of associated courses, seminars, and digital assets.
     *
     * @urlParam category_slug string required The slug of the category. Example: programming
     *
     * @queryParam per_page int Optional number of items per page for each product type. Default is 15. Example: 15
     *
     * @responseFile resources/responses/shop/products/categories/show.json
     */
    public function show(PaginationRequestData $data, Category $category, CategoryQueryService $service)
    {

        $courses       = $service->getProductsForCategory($category, ProductableEnum::COURSE, $data->per_page);
        $seminars      = $service->getProductsForCategory($category, ProductableEnum::SEMINAR, $data->per_page);
        $digitalAssets = $service->getProductsForCategory($category, ProductableEnum::DIGITAL_ASSET, $data->per_page);

        return apiResponse()->success(CategoryDetailData::from(
            [
                ...$category->toArray(),
                'courses'        => $courses,
                'seminars'       => $seminars,
                'digital_assets' => $digitalAssets,
            ]
        ));
    }
}
