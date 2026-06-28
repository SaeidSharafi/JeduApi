<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Shop\ProductDeliveryOptionPriceData;
use App\Data\Shop\ProductPriceData;
use App\Enums\Content\PublicationStatusEnum;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\ProductPrice;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final readonly class ProductPriceService
{
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
     * @param  Collection<Product>  $products
     * @return Collection A collection of ProductPriceData keyed by product ID.
     */
    public function getPriceDataForProducts(Collection $products): Collection
    {
        return $products->keyBy('id')->map(
            fn (Product $product): \App\Data\Shop\ProductPriceData => $this->getPriceDataForProduct($product)
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
        $deliveryOptions->each(function ($deliveryOption) use (&$prices): void {
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
        if ($discountPrice !== null) {
            $finalPrice = min($finalPrice, $discountPrice);
        }

        // 3. Determine the type of discount that resulted in the final price
        $discountAmount = null;
        $discountType   = null;

        if ($finalPrice < $standardPrice) {
            $discountAmount = $standardPrice - $finalPrice;

            if ($featuredPrice !== null && $featuredPrice <= ($discountPrice ?? PHP_INT_MAX)) {
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
     */
    public function updatePriceIndexForProducts(Collection $products): void
    {
        $priceIndexPayloads    = [];
        $productsToUpdateCache = [];

        foreach ($products as $product) {
            // Calculate the price data DTO
            $priceData = $this->calculatePriceDataForProduct($product, useCache: false);
            // Prepare the payload for the price index table
            $payload = $this->buildPriceIndexPayload($product, $priceData);

            if ($payload) {
                $priceIndexPayloads[] = $payload;
            }

            // Also update the JSON cache on the product model itself
            $product->price_data_cache = $priceData->toArray();
            $productsToUpdateCache[]   = $product;
        }

        // --- Bulk Operations for Performance ---

        // 1. Perform a single bulk UPSERT for the price index table
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

        // 2. Update the JSON cache on all products (uses one query per product)
        // This can't be a single query, but it's still efficient.
        foreach ($productsToUpdateCache as $product) {
            $product->saveQuietly();
        }
    }

    private function buildPriceIndexPayload(Product $product, ProductPriceData $priceData): ?array
    {
        $prices = collect($priceData->prices);

        if ($prices->isEmpty()) {
            ProductPrice::where('product_id', $product->id)->delete();

            return null;
        }

        return [
            'product_id'              => $product->id,
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

    // /**
    // * Get cached product-specific discount price.
    // */
    // private function getDiscountPrice(ProductDeliveryOption $option): ?int
    // {
    //    $discountRecord = $option->productDeliveryOptionDiscountPrice;
    //    if (!$discountRecord){
    //        return $option->price;
    //    }
    //    $now    = now();
    //    $starts = $discountRecord->starts_at;
    //    $ends   = $discountRecord->ends_at;
    //
    //    $isAfterStart = is_null($starts) || $now->greaterThanOrEqualTo($starts);
    //    $isBeforeEnd  = is_null($ends)   || $now->lessThanOrEqualTo($ends);
    //
    //    return ($isAfterStart && $isBeforeEnd) ? $discountRecord->discounted_price : $option->price;
    // }
}
