<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Product;
use App\Services\ProductPriceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use SmartCache\Facades\SmartCache;

final class UpdateProductPriceCacheJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $productId) {}

    public function handle(ProductPriceService $priceService): void
    {
        $product = Product::find($this->productId);
        $product->loadMissing([
            'productDeliveryOptions' => fn ($q) => $q->where('status', 'published'),
            'productDeliveryOptions.productDeliveryOptionDiscountPrice',
        ]);

        // 1. Use your existing service to calculate the rich price data.
        $priceData = $priceService->calculatePriceDataForProduct($product);

        // 2. Save the result to the cache column.
        $product->updateQuietly(['price_data_cache' => $priceData->toJson()]);
        $this->clearCachesForProduct($product);
    }

    private function clearCachesForProduct(Product $product): void
    {
        $keysToClear = config('cache_invalidation.map.'.Product::class, []);

        foreach ($keysToClear as $key) {
            SmartCache::forget($key->key());
        }
    }
}
