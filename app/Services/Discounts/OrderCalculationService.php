<?php

declare(strict_types=1);

namespace App\Services\Discounts;

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
use App\Services\ProductPriceService;
use InvalidArgumentException;
use RuntimeException;

final class OrderCalculationService
{
    public function __construct(
        private PromotionFinder $promotionFinder,
        private DiscountHandlerRegistry $handlerRegistry,
        private ProductPriceService $priceService
    ) {}

    /**
     * The main public method. Orchestrates the entire calculation.
     */
    public function calculate(OrderCreateData $data): OrderContextData
    {
        // 1. Build the initial state of the order before any discounts are applied.
        $context = $this->buildInitialContext($data);

        // 2. Find the promotion that should be applied (if any).
        $promotion = $this->promotionFinder->findApplicablePromotion($data->applied_coupon_code, $data->promotion_id);

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
    private function buildInitialContext(OrderCreateData $data, bool $useFreshData = false): OrderContextData
    {
        $customer = User::find($data->customer_id);
        $pdoIds   = collect($data->items)->pluck('product_delivery_option_id')->all();

        $deliveryOptions = ProductDeliveryOption::query()
            ->with([
                'product',
                'productDeliveryOptionDiscountPrice',  // Load discount prices for ProductPriceService
            ])
            ->findMany($pdoIds)
            ->keyBy('id');

        if (count($pdoIds) !== $deliveryOptions->count()) {
            // Find which ID(s) are missing to provide a more helpful error message, if desired.
            $missingIds = array_diff($pdoIds, $deliveryOptions->keys()->all());
            throw new InvalidArgumentException(
                __('messages.order.delivery_options_not_found', ['ids' => implode(', ', $missingIds)])
            );

        }

        $subtotal_all_items          = 0;
        $subtotal_full_payment_items = 0;
        $calculatedItems             = collect();
        foreach ($data->items as $itemData) {
            /** @var ProductDeliveryOption $option */
            $option = $deliveryOptions->get($itemData->product_delivery_option_id);

            $originalFullPrice = $option->price;
            // Use ProductPriceService for consistent pricing logic
            $priceData            = $this->priceService->getPriceDataForOption($option);
            $startingPriceForCalc = $priceData->current_price;

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
     * Iterates through all 'condition' rules and ensures they all pass.
     */
    private function allConditionsPass(DiscountPromotion $promotion, OrderContextData $context): bool
    {
        $conditionRules = $promotion->rules->where('type', 'condition');

        foreach ($conditionRules as $rule) {
            $handlerName  = data_get($rule, 'handler');
            $handlerClass = $this->handlerRegistry->getCartConditionHandler($handlerName);
            if (! $handlerClass) {
                throw new RuntimeException(__('messages.discount.no_condition_handler', ['name' => $handlerName]));
            }

            /** @var DiscountConditionContract $handler */
            $handler        = app($handlerClass);
            $configDtoClass = $this->handlerRegistry->getConfigClass($handlerClass);

            if (! $configDtoClass) {
                throw new RuntimeException(__('messages.discount.no_config_dto', ['class' => $handlerClass]));
            }

            $config = $configDtoClass::from(data_get($rule, 'configuration'));

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
