<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Product;

use App\Data\Shop\Product\ProductCardData;
use App\Enums\Product\RelationTypeEnum;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ProductPriceService;

/**
 * @group Shop - Product
 */
final class RelatedProductController extends Controller
{
    public function __construct(
        private readonly ProductPriceService $priceService,
    ) {}

    /**
     * Get related products for a given product and relation type.
     *
     * @urlParam product_slug string required The slug of the product. Example: example-product
     * @urlParam relation_type string required The type of relation (RELATED, CROSS_SELL, UPSELL). Example: RELATED
     *
     * @responseFile resources/responses/shop/products/related_products.json
     */
    public function __invoke(Product $product, RelationTypeEnum $relation_type): \App\Contracts\ApiResponseInterface
    {
        $relatedProducts = $product
            ->relatedProducts()
            ->wherePivot('relation_type', $relation_type->value)
            ->get(['related_product_id']);

        if ($relatedProducts->isEmpty()) {
            return apiResponse()->success([]);
        }

        $relatedProducts = Product::query()
            ->publishedAndVisible()
            ->hasPublishedDeliveryOption()
            ->publishedProductable()
            ->activeTerm()
            ->forListing()
            ->whereIn('id', $relatedProducts->pluck('related_product_id'))
            ->get()
            ->map(function (Product $product): ProductCardData {
                $priceData = $this->priceService->getPriceDataForProduct($product);

                return ProductCardData::fromModel($product, $priceData);
            });

        return apiResponse()->success($relatedProducts);
    }
}
