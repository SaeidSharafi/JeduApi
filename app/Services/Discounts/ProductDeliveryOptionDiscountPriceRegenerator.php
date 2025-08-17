<?php

declare(strict_types=1);

namespace App\Services\Discounts;

use App\Contracts\Discounts\ProductDiscountActionContract;
use App\Contracts\Discounts\ProductDiscountConditionContract;
use App\Models\DiscountPromotion;
use App\Models\ProductDeliveryOption;
use App\Models\ProductDeliveryOptionDiscountPrice;
use Illuminate\Support\Facades\DB;

class ProductDeliveryOptionDiscountPriceRegenerator
{
    public function __construct(
        private readonly DiscountHandlerRegistry $handlerRegistry
    ) {}

    /**
     * Regenerate the product_delivery_option_discount_prices table for all active promotions.
     * This is called after promotion create/update and via cron.
     */
    public function regenerate(): void
    {
        // Clear existing discount prices
        ProductDeliveryOptionDiscountPrice::truncate();

        // Get all active product-specific promotions
        $promotions = $this->getActiveProductSpecificPromotions();

        if ($promotions->isEmpty()) {
            return;
        }

        // Process delivery options in chunks for better performance
        ProductDeliveryOption::query()
            ->where('status', 'published')
            ->chunk(1000, function ($deliveryOptions) use ($promotions) {
                $this->processDeliveryOptionsChunk($deliveryOptions, $promotions);
            });
    }

    /**
     * Get all active product-specific promotions.
     */
    private function getActiveProductSpecificPromotions()
    {
        return DiscountPromotion::query()
            ->where('type', 'product_specific')
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->with(['rules'])
            ->get();
    }

    /**
     * Process a chunk of delivery options against all promotions.
     */
    private function processDeliveryOptionsChunk($deliveryOptions, $promotions): void
    {
        $recordsToInsert = [];

        foreach ($deliveryOptions as $option) {
            $bestPrice = $this->calculateBestDiscountPrice($option, $promotions);

            if ($bestPrice !== null && $bestPrice['price'] < $option->price) {
                $recordsToInsert[] = [
                    'product_delivery_option_id' => $option->id,
                    'discount_promotion_id' => $bestPrice['promotion_id'],
                    'discounted_price' => $bestPrice['price'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // Batch insert for better performance
        if (!empty($recordsToInsert)) {
            DB::table('product_delivery_option_discount_prices')->insert($recordsToInsert);
        }
    }

    /**
     * Calculate the best discount price for a delivery option across all promotions.
     */
    private function calculateBestDiscountPrice(ProductDeliveryOption $option, $promotions): ?array
    {
        $bestPrice = null;
        $bestPromotionId = null;
        $originalPrice = $option->price;

        foreach ($promotions as $promotion) {
            // Check if all conditions pass for this product
            if (!$this->allConditionsPass($promotion, $option)) {
                continue;
            }

            // Apply all actions to get the final discounted price
            $discountedPrice = $this->applyActions($promotion, $option, $originalPrice);

            // Keep track of the best (lowest) price
            if ($discountedPrice < $originalPrice && ($bestPrice === null || $discountedPrice < $bestPrice)) {
                $bestPrice = $discountedPrice;
                $bestPromotionId = $promotion->id;
            }
        }

        return $bestPrice !== null ? [
            'price' => $bestPrice,
            'promotion_id' => $bestPromotionId
        ] : null;
    }

    /**
     * Check if all conditions pass for a product-specific promotion.
     */
    private function allConditionsPass(DiscountPromotion $promotion, ProductDeliveryOption $option): bool
    {
        $conditionRules = $promotion->rules->where('type', 'condition');

        foreach ($conditionRules as $rule) {
            $handlerName = data_get($rule, 'handler');
            $handlerClass = $this->handlerRegistry->getProductConditionHandler($handlerName);

            if (!$handlerClass) {
                // If handler not found, log warning and skip this promotion
                \Log::warning("Product condition handler not found: {$handlerName}");
                return false;
            }

            $configDtoClass = $this->handlerRegistry->getConfigClass($handlerClass);
            if (!$configDtoClass) {
                \Log::warning("Config DTO not found for handler: {$handlerClass}");
                return false;
            }

            try {
                /** @var ProductDiscountConditionContract $handler */
                $handler = app($handlerClass);
                $config = $configDtoClass::from(data_get($rule, 'configuration'));

                if (!$handler->passes($option, $config)) {
                    return false; // If any condition fails, skip this promotion
                }
            } catch (\Exception $e) {
                \Log::error("Error evaluating product condition: {$e->getMessage()}");
                return false;
            }
        }

        return true;
    }

    /**
     * Apply all actions to calculate the final discounted price.
     */
    private function applyActions(DiscountPromotion $promotion, ProductDeliveryOption $option, int $basePrice): int
    {
        $currentPrice = $basePrice;
        $actionRules = $promotion->rules->where('type', 'action');

        foreach ($actionRules as $rule) {
            $handlerName = data_get($rule, 'handler');
            $handlerClass = $this->handlerRegistry->getProductActionHandler($handlerName);

            if (!$handlerClass) {
                \Log::warning("Product action handler not found: {$handlerName}");
                continue;
            }

            $configDtoClass = $this->handlerRegistry->getConfigClass($handlerClass);
            if (!$configDtoClass) {
                \Log::warning("Config DTO not found for handler: {$handlerClass}");
                continue;
            }

            try {
                /** @var ProductDiscountActionContract $handler */
                $handler = app($handlerClass);
                $config = $configDtoClass::from(data_get($rule, 'configuration'));

                // Create a temporary option with the current price for the calculation
                $tempOption = clone $option;
                $tempOption->price = $currentPrice;

                $currentPrice = $handler->apply($tempOption, $config);

                // Ensure price doesn't go below 0
                $currentPrice = max($currentPrice, 0);
            } catch (\Exception $e) {
                \Log::error("Error applying product action: {$e->getMessage()}");
            }
        }

        return $currentPrice;
    }
}
