<?php

declare(strict_types=1);

namespace App\Services\Discounts;

use App\Attributes\DiscountHandlerKey;
use App\Contracts\Discounts\DiscountActionContract;
use App\Contracts\Discounts\DiscountConditionContract;
use App\Data\Admin\Discounts\CalculatedOrderItemData;
use App\Data\Admin\Discounts\OrderContextData;
use App\Data\Admin\Order\OrderCreateData;
use App\Enums\Order\DiscountTypeEnum;
use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Models\DiscountPromotion;
use App\Models\ProductDeliveryOption;
use App\Models\User;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class OrderCalculationService
{
    public function __construct(
        protected PromotionFinder $promotionFinder,
        protected DiscountHandlerRegistry $handlerRegistry
    ) {}

    /**
     * The main public method. Orchestrates the entire calculation.
     */
    public function calculate(OrderCreateData $data): OrderContextData
    {
        // 1. Build the initial state of the order before any discounts are applied.
        $context = $this->buildInitialContext($data);

        // 2. Find the promotion that should be applied (if any).
        $promotion = $this->promotionFinder->findApplicablePromotion($data);

        if ($promotion && $promotion->type === DiscountTypeEnum::CART_CHECKOUT && $this->allConditionsPass($promotion, $context)) {
            $context->evaluating_promotion = $promotion;
            if ($data->applied_coupon_code) {
                $context->triggered_by_coupon_code = $data->applied_coupon_code;
            }
            $this->applyActions($promotion, $context);
        }

        return $context;
    }

    /**
     * Assembles the initial OrderContextData from the raw request data.
     */
    private function buildInitialContext(OrderCreateData $data): OrderContextData
    {
        $customer = User::findOrFail($data->customer_id);
        $pdoIds   = collect($data->items)->pluck('product_delivery_option_id')->all();

        $deliveryOptions = ProductDeliveryOption::query()
            ->with('product')
            ->findMany($pdoIds)
            ->keyBy('id');

        if (count($pdoIds) !== $deliveryOptions->count()) {
            // Find which ID(s) are missing to provide a more helpful error message, if desired.
            $missingIds = array_diff($pdoIds, $deliveryOptions->keys()->all());
            throw new InvalidArgumentException(
                'One or more ProductDeliveryOption IDs do not exist: '.implode(', ', $missingIds)
            );

        }

        $precalculatedPrices = DB::table('product_delivery_option_discount_prices')
            ->whereIn('product_delivery_option_id', $pdoIds)
            ->pluck('discounted_price', 'product_delivery_option_id');

        $subtotal_all_items          = 0;
        $subtotal_full_payment_items = 0;
        $calculatedItems = collect();
        foreach ($data->items as $itemData) {
            $option = $deliveryOptions->get($itemData->product_delivery_option_id);

            $originalFullPrice    = $option->price;
            $startingPriceForCalc = $this->getBasePrice($option, $precalculatedPrices);

            $initialLineItemTotal = $startingPriceForCalc * $itemData->qty_ordered;

            if ($itemData->payment_type === OrderItemPaymentTypeEnum::PRE_PAYMENT->value) {
                // If it's a prepayment, the bill is the prepayment amount.
                $initialLineItemTotal = $option->prepayment_amount * $itemData->qty_ordered;
            }

            $calculatedItem = new CalculatedOrderItemData(
                product_delivery_option: $option,
                qty: $itemData->qty_ordered,
                payment_type: OrderItemPaymentTypeEnum::tryFrom($itemData->payment_type),
                price: $startingPriceForCalc,
                total: $initialLineItemTotal,
            );
            $calculatedItems->push($calculatedItem);

            $subtotal_all_items += $originalFullPrice * $calculatedItem->qty;

            if ($calculatedItem->payment_type === OrderItemPaymentTypeEnum::FULL_PAYMENT) {
                $subtotal_full_payment_items += $startingPriceForCalc * $calculatedItem->qty;
            }
        }

        return new OrderContextData(
            customer: $customer,
            items: $calculatedItems,
            subtotal_full_payment_items: $subtotal_full_payment_items,
            subtotal_all_items: $subtotal_all_items,
        );
    }

    /**
     * Determines the correct starting price for an item based on the pricing hierarchy.
     *
     * Hierarchy:
     * 1. Pre-calculated 'product_specific' discount price.
     * 2. Active 'featured_price'.
     * 3. Standard 'price'.
     */
    private function getBasePrice(ProductDeliveryOption $option, \Illuminate\Support\Collection $precalculatedPrices): int
    {
        // 1. Highest priority: A pre-calculated discount from a product-specific sale.
        if ($precalculatedPrices->has($option->id)) {
            return $precalculatedPrices->get($option->id);
        }

        // 2. Second priority: An active featured price (sale price).
        if ($this->isFeaturedPriceActive($option)) {
            return $option->featured_price;
        }

        // 3. Fallback: The standard price.
        return $option->price;
    }

    /**
     * Iterates through all 'condition' rules and ensures they all pass.
     */
    private function allConditionsPass(DiscountPromotion $promotion, OrderContextData $context): bool
    {
        $conditionRules = $promotion->rules->where('type', 'condition');

        foreach ($conditionRules as $rule) {
            $handlerName = data_get($rule,'handler');
            $handlerClass = $this->handlerRegistry->getCartConditionHandler($handlerName);
            if (! $handlerClass) {
                throw new \RuntimeException("No discount condition handler registered for '{$handlerName}'");
            }

            /** @var DiscountConditionContract $handler */
            $handler        = app($handlerClass);
            $configDtoClass = $this->handlerRegistry->getConfigClass($handlerClass);

            if (! $configDtoClass) {
                throw new \RuntimeException("No config DTO mapped for handler '{$handlerClass}'");
            }

            $config = $configDtoClass::from(data_get($rule,'configuration'));

            if (! $handler->passes($context, $config)) {
                return false; // If any condition fails, the entire promotion is invalid.
            }
        }

        return true;
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
            $handlerName = data_get($rule,'handler');
            $handlerClass = $this->handlerRegistry->getCartActionHandler($handlerName);
            if (! $handlerClass) {
                throw new \RuntimeException("No discount action handler registered for '{$handlerName}'");
            }

            /** @var DiscountActionContract $handler */
            $handler        = app($handlerClass);
            $configDtoClass = $this->handlerRegistry->getConfigClass($handlerClass);

            if (! $configDtoClass) {
                throw new \RuntimeException("No config DTO mapped for handler '{$handlerClass}'");
            }

            $config = $configDtoClass::from(data_get($rule,'configuration'));
            // The action handler mutates the $context object directly.
            $handler->apply($context, $config);
        }

        // For CART_CHECKOUT type promotions, add cart-level discount information to audit trail
        if ($promotion->type === \App\Enums\Order\DiscountTypeEnum::CART_CHECKOUT) {
            // Calculate the discount amount applied by this specific promotion
            $discountAmountAfter = $context->items->sum('discount_amount');
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

    private function isFeaturedPriceActive(ProductDeliveryOption $option): bool
    {
        // 1. The Initial Check (Guard Clause)
        if (! $option->is_featured || is_null($option->featured_price)) {
            return false;
        }

        // 2. The Date Comparison Logic
        $now    = Carbon::now();
        $starts = $option->featured_price_start_date;
        $ends   = $option->featured_price_end_date;

        $isAfterStart = is_null($starts) || $now->greaterThanOrEqualTo($starts);
        $isBeforeEnd  = is_null($ends)   || $now->lessThanOrEqualTo($ends);

        // 3. The Final Decision
        return $isAfterStart && $isBeforeEnd;
    }
}
