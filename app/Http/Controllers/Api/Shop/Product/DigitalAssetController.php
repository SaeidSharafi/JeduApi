<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Product;

use App\Contracts\ApiResponseInterface;
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
     * Retrieve a paginated list of active digital assets with optional filtering and sorting.
     *
     * **Default Behavior**: Only digital assets currently available for content access are returned
     * unless explicitly overridden by filter parameters.
     *
     * **Availability Filters**:
     * - `filter.is_available_now=true`: Returns only currently available digital assets (default behavior)
     * - `filter.availability_status`: Filter by temporal state (past/upcoming/ongoing) - overrides is_available_now
     * - `filter.available_from` / `filter.available_to`: Custom availability date range
     *
     * **Registration Filters** (applied within available digital assets):
     * - `filter.registration_starts_after`: Registration opens on/after this date
     * - `filter.registration_ends_before`: Registration closes on/before this date
     *
     * @ignoreQueryParam type
     *
     * @responseFile resources/responses/shop/products/digital-assets/index.json
     */
    public function index(ProductListRequestData $requestData): ApiResponseInterface
    {
        $requestData->type = ProductableEnum::DIGITAL_ASSET->value;
        $courses           = ProductQueryService::make()
            ->getDigitalAssetList($requestData)
            ->through(function (Product $product) {
                $priceData = $this->priceService->getPriceDataForProduct($product);

                return ProductCardData::fromModel($product, $priceData);
            });

        return apiResponse()->success($courses);
    }

    /**
     * Digital Asset Detail
     *
     * Retrieve detailed information about a specific product of digital asset type by its slug.
     *
     * @responseFile  200 resources/responses/shop/products/digital-assets/show.json
     * @responseFile  404 resources/responses/404.json
     */
    public function show(Product $product): ApiResponseInterface
    {
        $product = ProductQueryService::make()
            ->ofType(ProductableEnum::DIGITAL_ASSET)
            ->availableProducts()
            ->forDetail()
            ->getQuery()
            ->where('id', $product->id)
            ->firstOrFail();

        $priceData = $this->priceService->getPriceDataForProduct($product);

        return apiResponse()->success(DigitalAssetDetailData::fromModel($product, $priceData));
    }
}
