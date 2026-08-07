<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Product;

use App\Data\Shop\Product\ProductDeliveryOptionCardData;
use App\Http\Controllers\Controller;
use App\Models\ProductDeliveryOption;
use App\Services\ProductPriceService;

/**
 * @group Shop - Product Delivery Options
 */
final class ProductDeliveryOptionController extends Controller
{
    /**
     * Get a product delivery option by UUID.
     *
     * @responseFile 200 resources/responses/shop/products/delivery_option.json
     */
    public function __invoke(ProductDeliveryOption $productDeliveryOption, ProductPriceService $priceService): ProductDeliveryOptionCardData
    {
        $productDeliveryOption->load('product.productable.media');

        return ProductDeliveryOptionCardData::fromModel($productDeliveryOption);
    }
}
