<?php

declare(strict_types=1);

namespace App\Services\Discounts;

use App\Enums\Order\DiscountTypeEnum;
use App\Models\DiscountPromotion;
use App\Models\ProductDeliveryOption;
use App\Models\ProductDeliveryOptionDiscountPrice;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Product Discount Indexer - manages the indexing of product discount prices.
 * Inspired by Bagisto's CatalogRuleIndex but simplified for our product discount system.
 */
final class ProductDiscountIndexer
{
    public function __construct(
        private readonly DiscountHandlerRegistry $handlerRegistry,
        private readonly ProductDiscountPriceCalculator $priceCalculator
    ) {}

    /**
     * Full re-index of all product discount prices.
     * This clears all existing discount prices and rebuilds them from scratch.
     */
    public function reIndexComplete(): void
    {
        DB::beginTransaction();
        // Clear all existing discount prices
        $this->cleanAllDiscountPrices();

        // Get all active promotions
        $promotions = $this->getActivePromotions();

        if ($promotions->isEmpty()) {
            return;
        }

        // Process products in chunks to avoid memory issues
        $this->indexProductDiscountPrices($promotions);
        DB::commit();

    }

    /**
     * Re-index discount prices for a specific promotion.
     * This is called when a promotion is created or updated.
     */
    public function reIndexPromotion(DiscountPromotion $promotion): void
    {
        DB::beginTransaction();

        // Clean existing indices for this promotion
        $this->cleanPromotionIndices($promotion);

        // Only process if the promotion is currently valid
        if ($this->isPromotionCurrentlyValid($promotion)) {
            // Get all active promotions (including this one) for layered calculation
            $allPromotions = $this->getActivePromotions();
            $this->indexProductDiscountPrices($allPromotions);
        }
        DB::commit();

    }

    /**
     * Re-index discount prices for specific product delivery options.
     * This is useful when products are updated or when cleaning up after a disabled promotion.
     */
    public function reIndexProductsByDeliveryOptionIds(Collection $deliveryOptionIds): void
    {
        // Get all active promotions for layered calculation
        $promotions = $this->getActivePromotions();

        if ($promotions->isEmpty()) {
            return;
        }

        // Get the specified product delivery options
        $deliveryOptions = ProductDeliveryOption::query()
            ->whereIn('id', $deliveryOptionIds)
            ->where('status', 'published')
            ->with(['product'])
            ->get();

        if ($deliveryOptions->isNotEmpty()) {
            $this->processProductChunk($deliveryOptions, $promotions);
        }

    }

    /**
     * Clean all discount price indices.
     */
    public function cleanAllDiscountPrices(): void
    {
        ProductDeliveryOptionDiscountPrice::truncate();
    }

    /**
     * Clean discount price indices for a specific promotion.
     */
    public function cleanPromotionIndices(DiscountPromotion $promotion): void
    {
        ProductDeliveryOptionDiscountPrice::where('discount_promotion_id', $promotion->id)->delete();
    }

    /**
     * Main method to index product discount prices using the layered promotion system.
     */
    private function indexProductDiscountPrices(Collection $promotions): void
    {
        ProductDeliveryOption::query()
            ->where('status', 'published')
            ->with(['product'])
            ->chunk(1000, function ($deliveryOptions) use ($promotions) {
                $this->processProductChunk($deliveryOptions, $promotions);
            });
    }

    /**
     * Process a chunk of product delivery options with layered promotion support.
     */
    private function processProductChunk(Collection $productDeliveryOptions, Collection $applicablePromotions): void
    {
        $recordsToUpsert = [];

        foreach ($productDeliveryOptions as $productDeliveryOption) {
            // Calculate the final layered discount price for this product
            $finalDiscountedPrice = $this->priceCalculator->calculateFinalDiscountedPrice(
                $productDeliveryOption,
                $applicablePromotions
            );

            // Only store if there's an actual discount
            if ($finalDiscountedPrice < $productDeliveryOption->price) {
                // Find the highest priority promotion that applies to this product
                $bestPromotion = $this->findBestApplicablePromotion($productDeliveryOption, $applicablePromotions);

                if ($bestPromotion) {
                    $recordsToUpsert[] = [
                        'product_delivery_option_id' => $productDeliveryOption->id,
                        'discount_promotion_id'      => $bestPromotion->id,
                        'discounted_price'           => $finalDiscountedPrice,
                        'created_at'                 => now(),
                        'updated_at'                 => now(),
                    ];
                }
            }
        }

        if (! empty($recordsToUpsert)) {
            // Use upsert to handle existing records
            ProductDeliveryOptionDiscountPrice::upsert(
                $recordsToUpsert,
                ['product_delivery_option_id'], // unique key
                ['discount_promotion_id', 'discounted_price', 'updated_at'] // columns to update
            );
        }
    }

    /**
     * Find the best applicable promotion for a product delivery option.
     * Returns the highest priority promotion that actually applies to the product.
     */
    private function findBestApplicablePromotion(
        ProductDeliveryOption $productDeliveryOption,
        Collection $promotions
    ): ?DiscountPromotion {
        foreach ($promotions as $promotion) {
            if ($this->doesPromotionApplyToProduct($productDeliveryOption, $promotion)) {
                return $promotion; // Promotions are already ordered by priority
            }
        }

        return null;
    }

    /**
     * Check if a promotion applies to a specific product delivery option.
     */
    private function doesPromotionApplyToProduct(
        ProductDeliveryOption $productDeliveryOption,
        DiscountPromotion $promotion
    ): bool {
        return $this->allConditionsPass($promotion, $productDeliveryOption);
    }

    /**
     * Get all active product-specific promotions ordered by priority.
     */
    private function getActivePromotions(): Collection
    {
        return DiscountPromotion::query()
            ->where('type', DiscountTypeEnum::PRODUCT_SPECIFIC)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->orderBy('priority', 'asc')
            ->with(['rules'])
            ->get();
    }

    /**
     * Check if a promotion is currently valid (within its time window).
     */
    private function isPromotionCurrentlyValid(DiscountPromotion $promotion): bool
    {
        $now = now();

        return $promotion->is_active && ($promotion->starts_at === null || $promotion->starts_at <= $now)
                                     && ($promotion->ends_at === null || $promotion->ends_at >= $now);
    }

    /**
     * Get a representative promotion ID for storing in the discount price table.
     * In our simplified system, we use the highest priority (lowest number) promotion that affected the price.
     */
    private function getRepresentativePromotionId(ProductDeliveryOption $option, Collection $promotions): int
    {
        $matchingPromotions = $promotions->filter(function (DiscountPromotion $promotion) use ($option) {
            return $this->allConditionsPass($promotion, $option);
        });

        // Return the highest priority (lowest priority number) promotion
        return $matchingPromotions->sortBy('priority')->first()?->id ?? $promotions->first()->id;
    }

    /**
     * Check if all conditions pass for a promotion on a product.
     * This is delegated to our existing logic.
     */
    private function allConditionsPass(DiscountPromotion $promotion, ProductDeliveryOption $option): bool
    {
        $conditionRules = $promotion->rules->where('type', 'condition');

        foreach ($conditionRules as $rule) {
            $handlerName  = data_get($rule, 'handler');
            $handlerClass = $this->handlerRegistry->getProductConditionHandler($handlerName);

            // @codeCoverageIgnoreStart
            if (! $handlerClass) {
                return false;
            }
            // @codeCoverageIgnoreEnd

            $configDtoClass = $this->handlerRegistry->getConfigClass($handlerClass);
            // @codeCoverageIgnoreStart
            if (! $configDtoClass) {
                return false;
            }
            // @codeCoverageIgnoreEnd

            try {
                $handler = app($handlerClass);
                $config  = $configDtoClass::from(data_get($rule, 'configuration'));

                if (! $handler->passes($option, $config)) {
                    return false;
                }
            }
            // @codeCoverageIgnoreStart
            catch (Exception $e) {
                return false;
            }
            // @codeCoverageIgnoreEnd
        }

        return true;
    }
}
