<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Shop\ProductPriceData;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\ProductDeliveryOptionDiscountPrice;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final readonly class ProductPriceService
{
    /**
     * Get pricing information for a product with all pricing logic centralized.
     * This follows the same hierarchy as OrderCalculationService::getBasePrice():
     * 1. Product-specific discount price (cached from promotions)
     * 2. Featured price (manual sale price)
     * 3. Standard price (default product price)
     */
    public function getPriceData(Product $product, ?int $selectedDeliveryOptionId = null): ProductPriceData
    {
        // Get the delivery option to work with
        $deliveryOption = $this->getDeliveryOption($product, $selectedDeliveryOptionId);

        if (!$deliveryOption) {
            return ProductPriceData::make(0, 0);
        }

        // Get all pricing components
        $standardPrice = $deliveryOption->price;
        $featuredPrice = $this->getFeaturedPrice($deliveryOption);
        $discountPrice = $this->getDiscountPrice($deliveryOption);

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

        return ProductPriceData::make(
            currentPrice: $currentPrice,
            originalPrice: $standardPrice,
            featuredPrice: $featuredPrice,
            discountAmount: $discountAmount,
            discountType: $discountType
        );
    }

    /**
     * Get just the current effective price for a product (most common use case).
     */
    public function getCurrentPrice(Product $product, ?int $selectedDeliveryOptionId = null): int
    {
        return $this->getPriceData($product, $selectedDeliveryOptionId)->current_price;
    }

    /**
     * Get the current effective price for a ProductDeliveryOption directly.
     * This is useful when you already have the ProductDeliveryOption object.
     */
    public function getCurrentPriceForOption(ProductDeliveryOption $option): int
    {
        // Get all pricing components
        $standardPrice = $option->price;
        $featuredPrice = $this->getFeaturedPrice($option);
        $discountPrice = $this->getDiscountPrice($option);

        // Apply pricing hierarchy: discount → featured → standard
        if ($discountPrice !== null) {
            return $discountPrice;
        } elseif ($featuredPrice !== null) {
            return $featuredPrice;
        }

        return $standardPrice;
    }

    /**
     * Get pricing data for multiple products efficiently.
     */
    public function getPriceDataForProducts(Collection $products): Collection
    {
        // Preload all necessary relationships
        $products->load([
            'productDeliveryOptions:id,product_id,price,featured_price,is_featured,featured_price_start_date,featured_price_end_date',
            'productDeliveryOptions.productDeliveryOptionDiscountPrice:product_delivery_option_id,discounted_price'
        ]);

        return $products->mapWithKeys(function (Product $product) {
            return [$product->id => $this->getPriceData($product)];
        });
    }

    /**
     * Check if a product has any type of active discount.
     */
    public function hasActiveDiscount(Product $product, ?int $selectedDeliveryOptionId = null): bool
    {
        $priceData = $this->getPriceData($product, $selectedDeliveryOptionId);
        return $priceData->has_discount || $priceData->has_featured_price;
    }

    /**
     * Get the price range for a product (if it has multiple delivery options).
     */
    public function getPriceRange(Product $product): array
    {
        $options = $product->productDeliveryOptions()
            ->where('status', 'published')
            ->get();

        if ($options->isEmpty()) {
            return ['min' => 0, 'max' => 0];
        }

        $prices = $options->map(function (ProductDeliveryOption $option) use ($product) {
            return $this->getCurrentPrice($product, $option->id);
        });

        return [
            'min' => $prices->min(),
            'max' => $prices->max(),
        ];
    }

    /**
     * Get the original price for a product.
     */
    public function getOriginalPrice(Product $product, ?int $selectedDeliveryOptionId = null): int
    {
        return $this->getPriceData($product, $selectedDeliveryOptionId)->original_price;
    }

    /**
     * Calculate the discount percentage.
     */
    public function getDiscountPercentage(Product $product, ?int $selectedDeliveryOptionId = null): float
    {
        $priceData = $this->getPriceData($product, $selectedDeliveryOptionId);

        if ($priceData->original_price <= 0 || !$priceData->has_discount) {
            return 0.0;
        }

        $discountAmount = $priceData->original_price - $priceData->current_price;

        return round(($discountAmount / $priceData->original_price) * 100, 1);
    }

    /**
     * Get the appropriate delivery option for pricing.
     */
    private function getDeliveryOption(Product $product, ?int $selectedDeliveryOptionId = null): ?ProductDeliveryOption
    {
        if ($selectedDeliveryOptionId) {
            return $product->productDeliveryOptions()
                ->where('id', $selectedDeliveryOptionId)
                ->first();
        }

        // Default to first available delivery option
        return $product->productDeliveryOptions()
            ->where('status', 'published')
            ->first();
    }

    /**
     * Get featured price if active.
     * Mirrors the logic from OrderCalculationService::isFeaturedPriceActive().
     */
    private function getFeaturedPrice(ProductDeliveryOption $option): ?int
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
        // Check if discount price relationship is loaded
        if ($option->relationLoaded('productDeliveryOptionDiscountPrice')) {
            return $option->productDeliveryOptionDiscountPrice?->discounted_price;
        }

        // Fallback to direct query if not loaded
        $discountPrice = ProductDeliveryOptionDiscountPrice::where('product_delivery_option_id', $option->id)
            ->first();

        return $discountPrice?->discounted_price;
    }
}
