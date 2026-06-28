<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Product;
use App\Services\CacheInvalidationService;
use App\Services\ProductPriceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class UpdateProductPricingJob implements ShouldQueue
{
    use \Illuminate\Foundation\Queue\Queueable;

    public function __construct(
        public array $productIds,
    ) {}

    public function handle(ProductPriceService $priceService): void
    {
        if (empty($this->productIds)) {
            return;
        }

        $products = Product::whereIn('id', $this->productIds)
            ->with([
                'productDeliveryOptions' => fn ($q) => $q->where('status', 'published'),
                'productDeliveryOptions.productDeliveryOptionDiscountPrice',
            ])
            ->get();

        if ($products->isEmpty()) {
            Log::warning('No valid products found for price update', ['product_ids' => $this->productIds]);

            return;
        }

        $priceService->updatePriceIndexForProducts($products);

        $this->clearCachesForProducts();

    }

    private function clearCachesForProducts(): void
    {
        $invalidationService = app(CacheInvalidationService::class);

        $invalidationConfig = config('cache_invalidation.map.'.Product::class, []);
        $invalidationService->invalidateForModel(Product::class, $invalidationConfig);

    }
}
