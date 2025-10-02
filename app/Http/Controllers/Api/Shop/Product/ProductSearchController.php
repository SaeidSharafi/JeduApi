<?php

namespace App\Http\Controllers\Api\Shop\Product;

use App\Data\Shop\Product\Course\ProductListRequestData;
use App\Data\Shop\Product\ProductCardData;
use App\Enums\Product\ProductableEnum;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Query\ProductQueryService;
use App\Services\ProductPriceService;

class ProductSearchController extends Controller
{
    public function __construct(
        protected ProductPriceService $priceService,
    )
    {
    }

    public function __invoke(ProductListRequestData $requestData)
    {
        $courses = ProductQueryService::make()
            ->globalSearchProductsScout($requestData)
            ->through(function ($product) {
                $priceData = $this->priceService->getPriceDataForProduct($product);
                return ProductCardData::fromModel($product, $priceData);
            });

        return response()->success($courses);
    }
}
