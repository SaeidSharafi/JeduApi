<?php

declare(strict_types=1);

namespace Tests\Support\Traits;

use App\Models\Product;
use App\Services\ProductPriceService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Collection;

trait CreatesModelsWithCachedData
{
    /**
     * Executes a factory's create method and then synchronously generates the price cache
     * for the resulting model(s). This is the most flexible and robust helper.
     *
     * @param  Factory  $factory  The factory instance to be executed.
     * @return Product|Collection
     */
    public function createWithPriceCache(Factory $factory)
    {
        // 1. Execute the factory's create method as defined in the test.
        // This will create the model(s) and run all chained `afterCreating` callbacks.
        $models = $factory->create();

        // 2. Determine if we created a single model or a collection.
        if ($models instanceof Collection) {
            // If it's a collection, iterate and update the cache for each one.
            $models->each(fn (Product $product) => $this->generatePriceCacheForProduct($product));
        } else {
            // If it's a single model, update its cache.
            $this->generatePriceCacheForProduct($models);
        }

        // 3. Return the fully processed and fresh model(s).
        return $models->fresh();
    }

    /**
     * The core logic for generating the cache for a single product.
     */
    private function generatePriceCacheForProduct(Product $product): void
    {
        // Ensure all relations are loaded before calculating.
        $product->loadMissing('productDeliveryOptions');

        $priceService = $this->app->make(ProductPriceService::class);
        $priceData    = $priceService->calculatePriceDataForProduct($product);
        $product->updateQuietly(['price_data_cache' => $priceData->toJson()]);
    }
}
