<?php

namespace App\Services;

use App\Data\Shop\ProductPriceData;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * A Singleton service to cache data for the duration of a single HTTP request.
 * This prevents fetching the same model or calculating the same data multiple times.
 */
class RequestDataCacheService
{
    /** @var Collection<int, Product> */
    private Collection $products;

    /** @var array<int, ProductPriceData> */
    private array $priceData = [];

    public function __construct()
    {
        $this->products = new Collection();
    }

    public function hasProduct(int $id): bool
    {
        return $this->products->has($id);
    }

    public function getProduct(int $id): ?Product
    {
        return $this->products->get($id);
    }

    public function storeProducts(Collection $products): void
    {
        // Use `union` to add new products without replacing existing ones.
        $this->products = $this->products->union($products->keyBy('id'));
    }

    public function hasPriceData(int $productId): bool
    {
        return isset($this->priceData[$productId]);
    }

    public function getPriceDataForProduct(int $productId): ?ProductPriceData
    {
        return $this->priceData[$productId] ?? null;
    }

    public function storeProductPriceData(int $productId, ProductPriceData $priceData): void
    {
        $this->priceData[$productId] = $priceData;
    }
}
