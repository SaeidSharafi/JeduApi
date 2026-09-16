<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Shop\ProductDeliveryOptionPriceData;
use App\Data\Shop\ProductPriceData;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Product\ProductableEnum;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\ProductPrice;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final readonly class ProductPriceService
{
    /**
     * Number of products per bulk UPDATE statement when persisting price_data_cache.
     * Keeps bound-parameter count and query size well under Postgres/MySQL limits.
     */
    private const CACHE_PERSIST_CHUNK_SIZE = 500;

    public function __construct(
        private RequestDataCacheService $requestCache
    ) {}

    /**
     * The single source of truth for getting a product's price data.
     * It prioritizes the pre-calculated cache and falls back to a live calculation if necessary.
     */
    public function getPriceDataForProduct(Product $product): ProductPriceData
    {
        // 1. Prioritize the cache.
        if (! empty($product->price_data_cache)) {
            // The cache is fresh and valid, use it.
            return ProductPriceData::from($product->price_data_cache);
        }

        // 2. Fallback: The cache is empty, so we must calculate it live.
        // This is the 0.0001% "emergency" case.
        return $this->calculatePriceDataForProduct($product);
    }

    /**
     * Efficiently get price data for a collection of products.
     *
     * @param  Collection<int, Product>  $products
     * @return Collection<int, ProductPriceData>
     */
    public function getPriceDataForProducts(Collection $products): Collection
    {
        return $products->keyBy('id')->map(
            fn (Product $product): ProductPriceData => $this->getPriceDataForProduct($product)
        );
    }

    /**
     * Get pricing information for a product with all pricing logic centralized.
     * This follows the same hierarchy as OrderCalculationService::getBasePrice():
     * 1. Product-specific discount price (cached from promotions)
     * 2. Featured price (manual sale price)
     * 3. Standard price (default product price)
     */
    public function calculatePriceDataForProduct(
        Product $product,
        ?int $selectedDeliveryOptionId = null,
        bool $useCache = true
    ): ProductPriceData {
        if ($useCache && $selectedDeliveryOptionId === null && $this->requestCache->hasPriceData($product->id)) {
            return $this->requestCache->getPriceDataForProduct($product->id);
        }

        // Get the delivery option to work with
        $deliveryOptions = $this->findDeliveryOptionsForProduct($product, $selectedDeliveryOptionId);
        if ($deliveryOptions->isEmpty()) {
            return ProductPriceData::make([]);
        }
        $prices = [];
        $deliveryOptions->each(function (ProductDeliveryOption $deliveryOption) use (&$prices): void {
            $priceData = $this->getPriceDataForOption($deliveryOption);
            $prices[]  = $priceData;
        });

        $productPriceData = ProductPriceData::make(
            prices: $prices,
            range: $this->getPriceRangeForProduct($product),
        );
        if ($useCache && $selectedDeliveryOptionId === null) {
            $this->requestCache->storeProductPriceData($product->id, $productPriceData);
        }

        return $productPriceData;
    }

    /**
     * Get just the current effective price for a product (most common use case).
     */
    public function getMinCurrentPrice(Product $product, ?int $selectedDeliveryOptionId = null): int
    {
        return $this->calculatePriceDataForProduct($product, $selectedDeliveryOptionId)->min_price;
    }

    /**
     * Get the current effective price for a ProductDeliveryOption directly.
     * This is useful when you already have the ProductDeliveryOption object.
     */
    public function getPriceDataForOption(ProductDeliveryOption $option): ProductDeliveryOptionPriceData
    {
        // Bundle PDOs always price at their reviewed selling price. They are
        // structurally barred from featured prices, product-level automatic
        // promotion discount prices, and prepayment, so their current price
        // is the plain `price` column no matter what legacy rows exist.
        if ($option->product?->productable_type === ProductableEnum::BUNDLE->value) {
            return ProductDeliveryOptionPriceData::make(
                currentPrice: $option->price,
                originalPrice: $option->price,
                uuid: $option->uuid,
            );
        }

        $standardPrice   = $option->price;
        $featuredPrice   = $this->getActiveFeaturedPrice($option);
        $discountPrice   = $option->discount_price;
        $prePaymentPrice = $option->is_prepayment_available ? $option->prepayment_amount : null;

        // 2. Determine the final effective price using the "Best Price Wins" model
        $finalPrice = $standardPrice;

        // Check if the featured price is a candidate
        if ($featuredPrice !== null) {
            $finalPrice = min($finalPrice, $featuredPrice);
        }

        // Check if the promotional discount price is a candidate
        $finalPrice = min($finalPrice, $discountPrice);

        // 3. Determine the type of discount that resulted in the final price
        $discountAmount = null;
        $discountType   = null;

        if ($finalPrice < $standardPrice) {
            $discountAmount = $standardPrice - $finalPrice;

            if ($featuredPrice !== null && $featuredPrice <= $discountPrice) {
                $discountType = 'featured';
            } else {
                $discountType = 'promotion';
            }
        }

        // 4. Return the final, consistent DTO
        return ProductDeliveryOptionPriceData::make(
            currentPrice: $finalPrice,
            originalPrice: $standardPrice,
            prePaymentPrice: $prePaymentPrice,
            featuredPrice: $featuredPrice,
            discountAmount: $discountAmount,
            discountType: $discountType,
            uuid: $option->uuid
        );

    }

    /**
     * Check if a product has any type of active discount.
     */
    public function hasActiveDiscount(Product $product, ?int $selectedDeliveryOptionId = null): bool
    {
        $priceData = $this->calculatePriceDataForProduct($product, $selectedDeliveryOptionId);

        return $priceData->has_discount || $priceData->has_featured_price;
    }

    /**
     * Get the price range for a product (if it has multiple delivery options).
     *
     * @return array{min: int, max: int}
     */
    public function getPriceRangeForProduct(Product $product): array
    {
        $options = $product->productDeliveryOptions
            ->where('status', PublicationStatusEnum::PUBLISHED);

        if ($options->isEmpty()) {
            return ['min' => 0, 'max' => 0];
        }

        $prices = $options->map(
            fn (ProductDeliveryOption $option): int => $this->getPriceDataForOption($option)->current_price
        );

        return [
            'min' => $prices->min(),
            'max' => $prices->max(),
        ];
    }

    /**
     * Get the original price for a product.
     */
    public function getMinimumOriginalPrice(Product $product, ?int $selectedDeliveryOptionId = null): int
    {
        return $this->calculatePriceDataForProduct($product, $selectedDeliveryOptionId)->min_original_price;
    }

    /**
     * Calculate the discount percentage.
     * we get the highest discount
     */
    public function getHighestDiscountPercentage(Product $product, ?int $selectedDeliveryOptionId = null): float
    {
        $priceData = $this->calculatePriceDataForProduct($product, $selectedDeliveryOptionId);

        return $priceData->discount_percentage ?? 0.0;
    }

    public function getCurrentPriceForOption(ProductDeliveryOption $option): int
    {
        return $this->getPriceDataForOption($option)->current_price;
    }

    /**
     * Update the price index table for a given product.
     * This should be called whenever product prices change.
     */
    public function updatePriceIndex(Product $product): void
    {
        // Load all necessary relations
        $product->loadMissing([
            'productDeliveryOptions' => fn ($q) => $q->where('status', PublicationStatusEnum::PUBLISHED),
            'productDeliveryOptions.productDeliveryOptionDiscountPrice',
        ]);
        $this->updatePriceIndexForProducts(collect([$product]));
    }

    /**
     * Update price index for multiple products efficiently.
     *
     * @param  Collection<int, Product>  $products
     */
    public function updatePriceIndexForProducts(Collection $products): void
    {
        $priceIndexPayloads    = [];
        $staleProductIds       = [];
        $productsToUpdateCache = [];

        foreach ($products as $product) {
            $priceData = $this->calculatePriceDataForProduct($product, useCache: false);
            $prices    = collect($priceData->prices);

            if ($prices->isEmpty()) {
                // No priced delivery options left — the index row (if any) is stale.
                // Collected and deleted in one batched statement below.
                $staleProductIds[] = $product->id;
            } else {
                $priceIndexPayloads[] = $this->buildPriceIndexPayload($product->id, $priceData, $prices);
            }

            // Update the JSON cache attribute in-memory. Callers (e.g.
            // UpdateProductPricingJob) diff this attribute before/after to
            // detect which products actually changed, so it must be set
            // here even though persistence happens in bulk.
            $product->price_data_cache = $priceData->toArray();
            $productsToUpdateCache[]   = $product;
        }

        if (! empty($priceIndexPayloads)) {
            ProductPrice::upsert(
                $priceIndexPayloads,
                ['product_id'], // Unique identifier to match on
                // Columns to update if a match is found
                [
                    'min_price', 'min_original_price', 'max_price', 'max_original_price',
                    'has_discount', 'has_featured_price', 'has_prepayment',
                    'discount_percentage', 'highest_discount_amount',
                ]
            );
        }

        if ($staleProductIds !== []) {
            ProductPrice::whereIn('product_id', $staleProductIds)->delete();
        }

        $this->persistPriceDataCaches($productsToUpdateCache);
    }

    /**
     * Persist the recalculated JSON cache of a batch of products in bulk.
     *
     * A partial `upsert()` does not work: `products` has NOT NULL columns
     * without defaults, so the implicit insert branch of upsert() is rejected
     * even when every row already exists. The rows already exist, so the
     * batch is applied by id via a single CASE-based UPDATE instead, chunked
     * to stay under Postgres's bound-parameter ceiling / MySQL's packet size.
     *
     * @param  array<int, Product>  $products
     */
    private function persistPriceDataCaches(array $products): void
    {
        if ($products === []) {
            return;
        }

        $isPgsql = DB::connection()->getDriverName() === 'pgsql';
        $now     = Carbon::now();

        foreach (array_chunk($products, self::CACHE_PERSIST_CHUNK_SIZE) as $chunk) {
            $cases            = [];
            $cacheBindings    = [];
            $productIdBinding = [];

            foreach ($chunk as $product) {
                $jsonPlaceholder    = $isPgsql ? 'cast(? as jsonb)' : '?';
                $cases[]            = 'when ? then '.$jsonPlaceholder;
                $cacheBindings[]    = $product->id;
                $cacheBindings[]    = json_encode($product->price_data_cache, JSON_THROW_ON_ERROR);
                $productIdBinding[] = (int) $product->id;

                // Keep the in-memory model in sync with the persisted updated_at.
                $product->setAttribute('updated_at', $now);
            }

            $placeholders = implode(', ', array_fill(0, count($productIdBinding), '?'));

            DB::update(
                'update products set price_data_cache = case id '.implode(' ', $cases).' end, updated_at = ? where id in ('.$placeholders.')',
                [...$cacheBindings, $now, ...$productIdBinding]
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPriceIndexPayload(int $productId, ProductPriceData $priceData, Collection $prices): array
    {
        return [
            'product_id'              => $productId,
            'min_price'               => $prices->min('current_price'),
            'min_original_price'      => $prices->min('original_price'),
            'max_price'               => $prices->max('current_price'),
            'max_original_price'      => $prices->max('original_price'),
            'has_discount'            => $priceData->has_discount,
            'has_featured_price'      => $priceData->has_featured_price,
            'has_prepayment'          => $priceData->has_pre_payment,
            'discount_percentage'     => $priceData->discount_percentage,
            'highest_discount_amount' => $priceData->highest_discount_amount,
        ];
    }

    /**
     * Get the appropriate delivery option for pricing.
     *
     * @return Collection<int, ProductDeliveryOption>
     */
    private function findDeliveryOptionsForProduct(
        Product $product,
        ?int $id = null
    ): Collection {
        $options = $product->productDeliveryOptions;

        if ($id) {
            return $options->where('id', $id);
        }

        // Default to first available delivery option
        return $options
            ->where('status', PublicationStatusEnum::PUBLISHED);
    }

    /**
     * Get featured price if active.
     * Mirrors the logic from OrderCalculationService::isFeaturedPriceActive().
     */
    private function getActiveFeaturedPrice(ProductDeliveryOption $option): ?int
    {
        // Guard clause - check if featured pricing is enabled
        if (! $option->is_featured || is_null($option->featured_price)) {
            return null;
        }

        // Check date ranges
        $now    = Carbon::now();
        $starts = $option->featured_price_start_date;
        $ends   = $option->featured_price_end_date;

        $isAfterStart = is_null($starts) || $now->greaterThanOrEqualTo($starts);
        $isBeforeEnd  = is_null($ends)   || $now->lessThanOrEqualTo($ends);

        return ($isAfterStart && $isBeforeEnd) ? $option->featured_price : null;
    }
}
