<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Events\ProductSearchIndexInvalidated;
use App\Models\Product;
use App\Services\CacheInvalidationService;
use App\Services\ProductPriceService;
use Illuminate\Contracts\Queue\ShouldQueue;
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

        $indexedValuesBefore = $products->mapWithKeys(static fn (Product $product): array => [
            $product->id => [
                'price'        => (int) ($product->price_data_cache['min_price'] ?? 0),
                'has_discount' => (bool) ($product->price_data_cache['has_discount'] ?? false),
            ],
        ]);

        $priceService->updatePriceIndexForProducts($products);

        $changedProductIds = $products
            ->filter(static function (Product $product) use ($indexedValuesBefore): bool {
                return $indexedValuesBefore->get($product->id) !== [
                    'price'        => (int) ($product->price_data_cache['min_price'] ?? 0),
                    'has_discount' => (bool) ($product->price_data_cache['has_discount'] ?? false),
                ];
            })
            ->pluck('id')
            ->all();

        if ($changedProductIds !== []) {
            ProductSearchIndexInvalidated::dispatch($changedProductIds);
        }

        $this->clearCachesForProducts();

    }

    private function clearCachesForProducts(): void
    {
        $invalidationService = app(CacheInvalidationService::class);

        $invalidationConfig = config('cache_invalidation.map.'.Product::class, []);
        $invalidationService->invalidateForModel(Product::class, $invalidationConfig);

    }
}
