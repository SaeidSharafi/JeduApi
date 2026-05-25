<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Product;

use App\Data\Shop\Product\Course\ProductListRequestData;
use App\Data\Shop\Product\DigitalAssetDetailData;
use App\Data\Shop\Product\ProductCardData;
use App\Enums\Product\ProductableEnum;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Query\ProductQueryService;
use App\Services\ProductPriceService;

/**
 * @group Shop - Products - Digital Assets
 *
 * APIs for retrieving digital asset in the shop.
 */
final class DigitalAssetController extends Controller
{
    public function __construct(
        private ProductPriceService $priceService,
    ) {}

    /**
     * Digital Assets List
     *
     * Retrieve a paginated list of active product of digital asset type with optional filtering and sorting.
     *
     * @ignoreQueryParam type
     *
     * @responseFile responses/shop/products/digital-assets/index.json
     */
    public function index(ProductListRequestData $requestData)
    {
        $requestData->type = ProductableEnum::DIGITAL_ASSET->value;
        $courses           = ProductQueryService::make()
            ->getDigitalAssetList($requestData)
            ->through(function (Product $product) {
                $priceData = $this->priceService->getPriceDataForProduct($product);

                return ProductCardData::fromModel($product, $priceData);
            });

        return response()->success($courses);
    }

    /**
     * Digital Asset Detail
     *
     * Retrieve detailed information about a specific product of digital asset type by its slug.
     *
     * @responseFile  200 responses/shop/products/digital-assets/show.json
     * @responseFile  404 responses/404.json
     */
    public function show(Product $product)
    {
        $product = ProductQueryService::make()
            ->ofType(ProductableEnum::DIGITAL_ASSET)
            ->availableProducts()
            ->forDetail()
            ->getQuery()
            ->where('id', $product->id)
            ->firstOrFail();

        $priceData = $this->priceService->getPriceDataForProduct($product);

        return response()->success(DigitalAssetDetailData::fromModel($product, $priceData));
    }
}
