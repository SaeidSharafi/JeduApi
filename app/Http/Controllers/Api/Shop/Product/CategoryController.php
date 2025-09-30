<?php

namespace App\Http\Controllers\Api\Shop\Product;

use App\Data\Shop\PaginationRequestData;
use App\Data\Shop\Product\Category\CategoryCardData;
use App\Data\Shop\Product\Category\CategoryDetailData;
use App\Data\Shop\Product\ProductCardData;
use App\Enums\Product\ProductableEnum;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Services\ProductPriceService;
use App\Services\Query\CategoryQueryService;
use App\Services\Shop\ProductQueryService;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $categories = Category::query()
            ->withCount([
                'products' => function ($query) {
                    return ProductQueryService::make()
                        ->setQuery($query)
                        ->availableProducts()
                        ->getQuery();
                }
            ])->get();

        return response()->success(CategoryCardData::collect($categories));
    }

    /**
     * Display the specified resource.
     */
    public function show(PaginationRequestData $data,Category $category, CategoryQueryService $service)
    {

        $courses = $service->getProductsForCategory($category, ProductableEnum::COURSE, $data->per_page);
        $seminars = $service->getProductsForCategory($category, ProductableEnum::SEMINAR,  $data->per_page);
        $digitalAssets = $service->getProductsForCategory($category, ProductableEnum::DIGITAL_ASSET,  $data->per_page);

        return response()->success(CategoryDetailData::from(
            [
                ...$category->toArray(),
                'courses'        => $courses,
                'seminars'       => $seminars,
                'digital_assets' => $digitalAssets,
            ]
        ));
    }
}
