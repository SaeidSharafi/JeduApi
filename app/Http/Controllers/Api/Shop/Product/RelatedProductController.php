<?php

namespace App\Http\Controllers\Api\Shop\Product;

use App\Data\Shop\Product\ProductCardData;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Product\RelationTypeEnum;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Query\ProductQueryService;
use App\Services\ProductPriceService;

/**
 * @group Shop - Product
 */
class RelatedProductController extends Controller
{
    public function __construct(
        private readonly ProductQueryService $productQueryService,
        private readonly ProductPriceService $priceService,
    )
    {
    }

    /**
     * Get related products for a given product and relation type.
     *
     * @urlParam product_slug string required The slug of the product. Example: example-product
     * @urlParam relation_type string required The type of relation (RELATED, CROSS_SELL, UPSELL). Example: RELATED
     *
     * @responseFile storage/responses/shop/products/related_products.json
     */
    public function __invoke(Product $product, RelationTypeEnum $relation_type)
    {
        $relatedProducts = $product
            ->relatedProducts()
            ->wherePivot('relation_type', $relation_type->value)
            ->get(['related_product_id']);

        if ($relatedProducts->isEmpty()){
            return response()->success([]);
        }

        $relatedProducts = $this->productQueryService
            ->availableProducts()
            ->forListing()
            ->getQuery()
            ->whereIn('id', $relatedProducts->pluck('related_product_id'))
            ->get()
            ->map(function (Product $product) {
                $priceData = $this->priceService->getPriceDataForProduct($product);

                return ProductCardData::fromModel($product, $priceData);
            });

        return response()->success($relatedProducts);
    }
}
