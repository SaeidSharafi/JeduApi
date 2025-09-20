<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Shop\ProductDeliveryOptionPriceData;
use App\Data\Shop\ProductPriceData;
use App\Enums\PublicationStatusEnum;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final readonly class ProductPriceService
{
    public function __construct(
        private RequestDataCacheService $requestCache
    ) {
    }

    /**
     * Get pricing information for a product with all pricing logic centralized.
     * This follows the same hierarchy as OrderCalculationService::getBasePrice():
     * 1. Product-specific discount price (cached from promotions)
     * 2. Featured price (manual sale price)
     * 3. Standard price (default product price)
     */
    public function getPriceDataForProduct(Product $product, ?int $selectedDeliveryOptionId = null): ProductPriceData
    {
        if ($selectedDeliveryOptionId === null && $this->requestCache->hasPriceData($product->id)) {
            return $this->requestCache->getPriceDataForProduct($product->id);
        }

        // Get the delivery option to work with
        $deliveryOptions = $this->findDeliveryOptionsForProduct($product, $selectedDeliveryOptionId);
        if ($deliveryOptions->isEmpty()) {
            $optionPirces = [ProductDeliveryOptionPriceData::make(0, 0)];
            return ProductPriceData::make(
                $optionPirces
            );
        }
        $prices = [];
        $deliveryOptions->each(function ($deliveryOption) use (&$prices): void{
            $priceData = $this->getPriceDataForOption($deliveryOption);
            $prices[] = $priceData;
        });

        $productPriceData = ProductPriceData::make(
            prices: $prices,
            range: $this->getPriceRangeForProduct($product),
        );
        if ($selectedDeliveryOptionId === null) {
            $this->requestCache->storeProductPriceData($product->id, $productPriceData);
        }

        return $productPriceData;
    }

    /**
     * Get just the current effective price for a product (most common use case).
     */
    public function getMinCurrentPrice(Product $product, ?int $selectedDeliveryOptionId = null): int
    {
        return $this->getPriceDataForProduct($product, $selectedDeliveryOptionId)->min_price;
    }

    /**
     * Get the current effective price for a ProductDeliveryOption directly.
     * This is useful when you already have the ProductDeliveryOption object.
     */
    public function getPriceDataForOption(ProductDeliveryOption $option): ProductDeliveryOptionPriceData
    {
        // Get all pricing components
        $standardPrice = $option->price;
        $featuredPrice = $this->getActiveFeaturedPrice($option);
        $discountPrice = $this->getDiscountPrice($option);
        $prePaymentPrice = $option->is_prepayment_available ? $option->prepayment_amount : null;
        // Determine current price following hierarchy
        $currentPrice = $standardPrice;
        $discountAmount = null;
        $discountType = null;

        // Apply pricing hierarchy
        if ($discountPrice !== null) {
            // Highest priority: Product-specific discount
            $currentPrice = $discountPrice;
            $discountAmount = $standardPrice - $discountPrice;
            $discountType = 'promotion';
        } elseif ($featuredPrice !== null) {
            // Second priority: Featured price
            $currentPrice = $featuredPrice;
            $discountAmount = $standardPrice - $featuredPrice;
            $discountType = 'featured';
        }

        return ProductDeliveryOptionPriceData::make(
            currentPrice: $currentPrice,
            originalPrice: $standardPrice,
            prePaymentPrice: $prePaymentPrice,
            featuredPrice: $featuredPrice,
            discountAmount: $discountAmount,
            discountType: $discountType
        );

    }

    /**
     * Get pricing data for multiple products efficiently.
     */
    public function getPriceDataForProducts(\Illuminate\Database\Eloquent\Collection $products): Collection
    {
        // Preload all necessary relationships
        $products->loadMissing([
            'productDeliveryOptions',
            'productDeliveryOptions.productDeliveryOptionDiscountPrice:product_delivery_option_id,discounted_price'
        ]);

        return $products->mapWithKeys(function (Product $product) {
            return [$product->id => $this->getPriceDataForProduct($product)];
        });
    }

    /**
     * Check if a product has any type of active discount.
     */
    public function hasActiveDiscount(Product $product, ?int $selectedDeliveryOptionId = null): bool
    {
        $priceData = $this->getPriceDataForProduct($product, $selectedDeliveryOptionId);
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
            fn(ProductDeliveryOption $option): int => $this->getPriceDataForOption($option)->current_price
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
        return $this->getPriceDataForProduct($product, $selectedDeliveryOptionId)->min_original_price;
    }

    /**
     * Calculate the discount percentage.
     * we get the highest discount
     */
    public function getHighestDiscountPercentage(Product $product, ?int $selectedDeliveryOptionId = null): float
    {
        $priceData = $this->getPriceDataForProduct($product, $selectedDeliveryOptionId);

        return $priceData->discount_percentage ?? 0.0;
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
        if (!$option->is_featured || is_null($option->featured_price)) {
            return null;
        }

        // Check date ranges
        $now = Carbon::now();
        $starts = $option->featured_price_start_date;
        $ends = $option->featured_price_end_date;

        $isAfterStart = is_null($starts) || $now->greaterThanOrEqualTo($starts);
        $isBeforeEnd = is_null($ends) || $now->lessThanOrEqualTo($ends);

        return ($isAfterStart && $isBeforeEnd) ? $option->featured_price : null;
    }

    /**
     * Get cached product-specific discount price.
     */
    private function getDiscountPrice(ProductDeliveryOption $option): ?int
    {
        // the productDeliveryOptionDiscountPrice should be loaded via eager loading
        return $option->productDeliveryOptionDiscountPrice?->discounted_price;
    }

    public function getCurrentPriceForOption(ProductDeliveryOption $option): int
    {
        return $this->getPriceDataForOption($option)->current_price;
    }
}
