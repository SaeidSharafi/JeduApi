<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Product;

use App\Data\Shop\Product\Course\ProductListRequestData;
use App\Data\Shop\Product\ProductCardData;
use App\Data\Shop\Product\SeminarDetailData;
use App\Enums\Product\ProductableEnum;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Query\ProductQueryService;
use App\Services\ProductPriceService;

/**
 * @group Shop - Products - Seminars
 *
 * APIs for retrieving seminars in the shop.
 */
final class SeminarController extends Controller
{
    public function __construct(
        private ProductPriceService $priceService,
    ) {}

    /**
     * Seminar List
     *
     * Retrieve a paginated list of active product of seminar type with optional filtering and sorting.
     *
     * @ignoreQueryParam type
     *
     * @responseFile responses/shop/products/seminars/index.json
     */
    public function index(ProductListRequestData $requestData)
    {
        $requestData->type = ProductableEnum::SEMINAR->value;
        $courses           = ProductQueryService::make()
            ->getSeminarList($requestData)
            ->through(function (Product $product) {
                $priceData = $this->priceService->getPriceDataForProduct($product);

                return ProductCardData::fromModel($product, $priceData);
            });

        return response()->success($courses);
    }

    /**
     * Seminar Detail
     *
     * Retrieve detailed information about a specific product of seminar type by its slug.
     *
     * @responseFile  200 responses/shop/products/seminars/show.json
     * @responseFile  404 responses/404.json
     */
    public function show(Product $product)
    {
        $product = ProductQueryService::make()
            ->ofType(ProductableEnum::SEMINAR)
            ->availableProducts()
            ->forDetail()
            ->getQuery()
            ->where('id', $product->id)
            ->firstOrFail();

        $priceData = $this->priceService->getPriceDataForProduct($product);

        return response()->success(SeminarDetailData::fromModel($product, $priceData));
    }
}
