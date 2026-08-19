<?php

declare(strict_types=1);

namespace App\Services\Discounts;

use App\Contracts\Discounts\DiscountActionContract;
use App\Data\Admin\Discounts\OrderContextData;
use App\Data\Admin\Order\OrderCreateData;
use App\Enums\Order\DiscountTypeEnum;
use App\Models\DiscountPromotion;
use RuntimeException;

final class OrderCalculationService
{
    public function __construct(
        private PromotionService $promotionService,
        private DiscountHandlerRegistry $handlerRegistry,
    ) {}

    /**
     * The main public method. Orchestrates the entire calculation.
     */
    public function calculate(OrderCreateData $data): OrderContextData
    {
        // 1. Build the initial state of the order before any discounts are applied.
        $context = $this->promotionService->buildOrderContext($data);

        // 2. Find all applicable CART_CHECKOUT promotions (stacked by priority).
        $promotions = $this->promotionService->findAllApplicableCartPromotions(
            $data->applied_coupon_code,
        );

        // 3. Apply each matching promotion in priority order, respecting stop_processing_subsequent_rules.
        foreach ($promotions as $promotion) {
            if (! $this->promotionService->promotionConditionsPass($promotion, $context)) {
                continue; // Skip promotions whose conditions don't match
            }

            $context->evaluating_promotion = $promotion;
            // Attribute the coupon code only to the promotion that requires it;
            // automatic promotions must not inherit it in the audit trail.
            $context->triggered_by_coupon_code = $promotion->requires_coupon
                ? $data->applied_coupon_code
                : null;
            $this->applyActions($promotion, $context);

            if ($promotion->stop_processing_subsequent_rules) {
                break; // Stop stacking further promotions
            }
        }

        return $context;
    }

    /**
     * Iterates through all 'action' rules and applies them to the OrderContextData.
     */
    private function applyActions(DiscountPromotion $promotion, OrderContextData $context): void
    {
        // Track discount amounts before applying actions (for cart-level audit trail)
        $discountAmountBefore = $context->items->sum('discount_amount');

        $actionRules = $promotion->rules->where('type', 'action');

        foreach ($actionRules as $rule) {
            $handlerName  = data_get($rule, 'handler');
            $handlerClass = $this->handlerRegistry->getCartActionHandler($handlerName);
            if (! $handlerClass) {
                throw new RuntimeException(__('messages.discount.no_action_handler', ['name' => $handlerName]));
            }

            /** @var DiscountActionContract $handler */
            $handler        = app($handlerClass);
            $configDtoClass = $this->handlerRegistry->getConfigClass($handlerClass);

            if (! $configDtoClass) {
                throw new RuntimeException(__('messages.discount.no_config_dto', ['class' => $handlerClass]));
            }

            $config = $configDtoClass::from(data_get($rule, 'configuration'));
            // The action handler mutates the $context object directly.
            $handler->apply($context, $config);
        }

        // For CART_CHECKOUT type promotions, add cart-level discount information to audit trail
        if ($promotion->type === DiscountTypeEnum::CART_CHECKOUT) {
            // Calculate the discount amount applied by this specific promotion
            $discountAmountAfter   = $context->items->sum('discount_amount');
            $appliedDiscountAmount = $discountAmountAfter - $discountAmountBefore;

            if ($appliedDiscountAmount > 0) {
                $context->applied_cart_discounts[] = [
                    'promotion_id'   => $promotion->id,
                    'promotion_name' => $promotion->name,
                    'applied_amount' => $appliedDiscountAmount,
                    'coupon_code'    => $context->triggered_by_coupon_code,
                ];
            }
        }
    }
}
