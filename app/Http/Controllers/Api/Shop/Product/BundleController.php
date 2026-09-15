<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Product;

use App\Contracts\ApiResponseInterface;
use App\Data\Shop\Product\Bundle\BundleComponentData;
use App\Data\Shop\Product\Bundle\BundleDetailData;
use App\Data\Shop\Product\Bundle\BundleOptionData;
use App\Data\Shop\Product\Course\ProductListRequestData;
use App\Data\Shop\Product\ProductCardData;
use App\Enums\Product\ProductableEnum;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Query\ProductQueryService;
use App\Services\BundleAvailabilityService;
use App\Services\ProductPriceService;

/**
 * @group Shop - Products - Bundles
 *
 * APIs for the dedicated Bundle storefront.
 */
final class BundleController extends Controller
{
    public function __construct(
        private ProductPriceService $priceService,
        private BundleAvailabilityService $availabilityService,
    ) {}

    /**
     * List currently saleable Bundles using pagination only.
     *
     * @responseFile 200 resources/responses/shop/bundles/index.json
     */
    public function index(ProductListRequestData $requestData): ApiResponseInterface
    {
        $bundles = ProductQueryService::make()
            ->ofType(ProductableEnum::BUNDLE)
            ->availableBundles()
            ->forListing()
            ->paginate($requestData->per_page ?? 15)
            ->through(fn (Product $product): ProductCardData => ProductCardData::fromModel(
                $product,
                $this->priceService->getPriceDataForProduct($product),
            ));

        return apiResponse()->success($bundles);
    }

    /**
     * Retrieve a Bundle by its Product slug.
     *
     * @responseFile 200 resources/responses/shop/bundles/show.json
     * @responseFile 404 resources/responses/404.json
     */
    public function show(Product $product): ApiResponseInterface
    {
        $product = ProductQueryService::make()
            ->ofType(ProductableEnum::BUNDLE)
            ->availableProducts()
            ->forDetail()
            ->getQuery()
            ->with([
                'productDeliveryOptions' => fn ($query) => $query->with([
                    'product.productable',
                    'product.term',
                    'teachers',
                    'bundleComponents.product.productable',
                    'bundleComponents.product.term',
                    'bundleComponents.product.productDeliveryOptions',
                    'bundleComponents.teachers',
                ]),
            ])
            ->whereKey($product->id)
            ->firstOrFail();

        $product->productable->loadMediaWithVariantsMatchAll();

        $options = $product->productDeliveryOptions
            ->filter(fn ($option): bool => $this->availabilityService->isAvailable($option))
            ->map(function ($option): BundleOptionData {
                $components = $option->bundleComponents->map(
                    fn ($component): BundleComponentData => BundleComponentData::fromModel(
                        $component,
                        (int) $component->pivot->allocation,
                        $this->availabilityService->isAvailable($component),
                    )
                )->values();

                return BundleOptionData::fromModel(
                    $option,
                    $components,
                    (int) $components->sum(fn (BundleComponentData $component): int => $this->priceService->getMinCurrentPrice(
                        $option->bundleComponents->firstWhere('uuid', $component->uuid)->product,
                    )),
                    true,
                    $this->availabilityService->remainingCapacity($option),
                );
            })->values();

        if ($options->isEmpty()) {
            abort(404);
        }

        return apiResponse()->success(BundleDetailData::fromModel($product, $options));
    }
}
