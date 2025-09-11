<?php

declare(strict_types=1);

namespace App\Services\Discounts;

use App\Models\DiscountPromotion;
use App\Models\ProductDeliveryOption;
use Exception;
use Illuminate\Support\Collection;
use Log;

/**
 * Product Discount Price Calculator - handles the core logic for layered promotion calculations.
 * Inspired by Bagisto's price calculation system but adapted for our layered promotion approach.
 */
final class ProductDiscountPriceCalculator
{
    public function __construct(
        private readonly DiscountHandlerRegistry $handlerRegistry
    ) {}

    /**
     * Calculate the final discounted price for a product by applying all matching promotions sequentially.
     * This is the core of the layered promotion system inspired by Bagisto's catalog rules.
     */
    public function calculateFinalDiscountedPrice(ProductDeliveryOption $option, Collection $promotions): int
    {
        // Start with the original price
        $currentPrice = $option->price;

        // Find all promotions that match this product
        $matchingPromotions = $promotions->filter(function (DiscountPromotion $promotion) use ($option) {
            return $this->allConditionsPass($promotion, $option);
        });

        if ($matchingPromotions->isEmpty()) {
            return $currentPrice;
        }

        // Sort by priority (ascending - lower numbers = higher priority in our system)
        $sortedPromotions = $matchingPromotions->sortBy('priority');

        // Apply each promotion sequentially
        foreach ($sortedPromotions as $promotion) {
            $currentPrice = $this->applyPromotionActions($promotion, $option, $currentPrice);

            // If this promotion has end_other_rules = true, stop processing further promotions
            if ($promotion->stop_processing_subsequent_rules) {
                break;
            }
        }

        return $currentPrice;
    }

    /**
     * Apply all actions from a single promotion to calculate the discounted price.
     * Similar to Bagisto's calculate method but adapted for our handler system.
     */
    public function applyPromotionActions(
        DiscountPromotion $promotion,
        ProductDeliveryOption $option,
        int $basePrice
    ): int {
        $currentPrice = $basePrice;
        $actionRules  = $promotion->rules->where('type', 'action');

        foreach ($actionRules as $rule) {
            $handlerName  = data_get($rule, 'handler');
            $handlerClass = $this->handlerRegistry->getProductActionHandler($handlerName);

            // @codeCoverageIgnoreStart
            if (! $handlerClass) {
                Log::warning("Product action handler not found: {$handlerName}");

                continue;
            }
            // @codeCoverageIgnoreEnd

            $configDtoClass = $this->handlerRegistry->getConfigClass($handlerClass);

            // @codeCoverageIgnoreStart
            if (! $configDtoClass) {
                Log::warning("Config DTO not found for handler: {$handlerClass}");

                continue;
            }
            // @codeCoverageIgnoreEnd
            try {
                $handler = app($handlerClass);
                $config  = $configDtoClass::from(data_get($rule, 'configuration'));

                // Create a temporary option with the current price for the calculation
                $tempOption        = clone $option;
                $tempOption->price = $currentPrice;

                $currentPrice = $handler->apply($tempOption, $config);

                // Ensure price doesn't go below 0
                $currentPrice = max($currentPrice, 0);
            }
            // @codeCoverageIgnoreStart
            catch (Exception $e) {
                Log::error("Error applying product action: {$e->getMessage()}");
            }
            // @codeCoverageIgnoreEnd
        }

        return $currentPrice;
    }

    /**
     * Find which promotions were actually applied to achieve a specific final price.
     * This is used for audit trail purposes.
     *
     * @codeCoverageIgnore
     */
    public function findAppliedPromotionsForPrice(
        ProductDeliveryOption $option,
        Collection $promotions,
        int $targetPrice
    ): Collection {
        $appliedPromotions = collect();
        $currentPrice      = $option->price;

        $matchingPromotions = $promotions->filter(function (DiscountPromotion $promotion) use ($option) {
            return $this->allConditionsPass($promotion, $option);
        })->sortBy('priority');

        foreach ($matchingPromotions as $promotion) {
            $priceAfterPromotion = $this->applyPromotionActions($promotion, $option, $currentPrice);

            if ($priceAfterPromotion < $currentPrice) {
                $appliedPromotions->push($promotion);
                $currentPrice = $priceAfterPromotion;

                if ($promotion->stop_processing_subsequent_rules) {
                    break;
                }

                // If we've reached the target price, we can stop
                if ($currentPrice <= $targetPrice) {
                    break;
                }
            }
        }

        return $appliedPromotions;
    }

    /**
     * Check if all conditions pass for a product-specific promotion.
     */
    private function allConditionsPass(DiscountPromotion $promotion, ProductDeliveryOption $option): bool
    {
        $conditionRules = $promotion->rules->where('type', 'condition');

        foreach ($conditionRules as $rule) {
            $handlerName  = data_get($rule, 'handler');
            $handlerClass = $this->handlerRegistry->getProductConditionHandler($handlerName);

            // @codeCoverageIgnoreStart
            if (! $handlerClass) {
                Log::warning("Product condition handler not found: {$handlerName}");

                return false;
            }
            // @codeCoverageIgnoreEnd

            $configDtoClass = $this->handlerRegistry->getConfigClass($handlerClass);

            // @codeCoverageIgnoreStart
            if (! $configDtoClass) {
                Log::warning("Config DTO not found for handler: {$handlerClass}");

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
                Log::error("Error evaluating product condition: {$e->getMessage()}");

                return false;
            }
            // @codeCoverageIgnoreEnd
        }

        return true;
    }
}
