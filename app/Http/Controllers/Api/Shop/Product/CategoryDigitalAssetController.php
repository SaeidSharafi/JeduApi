<?php

namespace App\Http\Controllers\Api\Shop\Product;

use App\Data\Shop\PaginationRequestData;
use App\Enums\Product\ProductableEnum;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\Query\CategoryQueryService;


class CategoryDigitalAssetController extends Controller
{

    /**
     * Display the specified resource.
     */
    public function __invoke(PaginationRequestData $data,Category $category, CategoryQueryService $service)
    {
        $paginatedCourses = $service->getProductsForCategory($category, ProductableEnum::DIGITAL_ASSET, $data->per_page,true);

        return response()->success($paginatedCourses);
    }
}
