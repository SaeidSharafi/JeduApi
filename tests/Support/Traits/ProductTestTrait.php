<?php

declare(strict_types=1);

namespace Tests\Support\Traits;

use App\Models\Product;
use App\Services\ProductPriceService;

trait ProductTestTrait
{
    protected function indexProductPrice(Product $product): Product
    {
        /** @var ProductPriceService $service */
        $service = app(ProductPriceService::class);
        $service->updatePriceIndex($product->fresh());

        return $product->fresh(['productPrice', 'productDeliveryOptions.productDeliveryOptionDiscountPrice']);
    }
}
