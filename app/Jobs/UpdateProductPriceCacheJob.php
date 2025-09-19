<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\ProductPriceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateProductPriceCacheJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $productId) {}

    public function handle(ProductPriceService $priceService): void
    {
        $product = Product::find($this->productId);
        $product->loadMissing([
            'productDeliveryOptions' => fn($q) => $q->where('status', 'published'),
            'productDeliveryOptions.productDeliveryOptionDiscountPrice'
        ]);

        // 1. Use your existing service to calculate the rich price data.
        $priceData = $priceService->getPriceDataForProduct($product);

        // 2. Save the result to the cache column.
        $product->updateQuietly(['price_data_cache' => $priceData->toJson()]);
    }
}
