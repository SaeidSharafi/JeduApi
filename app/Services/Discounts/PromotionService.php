<?php

declare(strict_types=1);

namespace App\Services\Discounts;

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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use RuntimeException;

final class PromotionService
{
    public function __construct(
        private DiscountHandlerRegistry $handlerRegistry,
        private ProductPriceService $priceService,
    ) {}

    // ──────────────────────────────────────────────
    //  Finding
    // ──────────────────────────────────────────────

    /**
     * Find a single active promotion by its coupon code.
     * Used by CartService to locate a promotion when a coupon is entered.
     */
    public function findPromotionByCoupon(string $couponCode): ?DiscountPromotion
    {
        return DiscountPromotion::query()
            ->where('is_active', true)
            ->where(function ($q): void {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q): void {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->where(function ($q): void {
                $q->whereNull('usage_limit_total')
                    ->orWhereColumn('total_usage_count', '<', 'usage_limit_total');
            })
            ->whereHas('coupons', fn (Builder $q) => $q
                ->where('code', $couponCode)
                ->where('is_active', true)
                ->where(function (Builder $q2): void {
                    $q2->whereNull('usage_limit')
                        ->orWhereColumn('usage_count', '<', 'usage_limit');
                })
            )
            ->with('rules')
            ->first();
    }

    /**
     * Find ALL applicable CART_CHECKOUT promotions for stacking.
     * Returns promotions sorted by priority ASC.
     *
     * A promotion with requires_coupon = true needs a matching coupon to activate.
     * A promotion with requires_coupon = false is always evaluated subject to its conditions.
     */
    public function findAllApplicableCartPromotions(
        ?string $appliedCouponCode = null,
    ): Collection {
        $query = DiscountPromotion::query()
            ->where('type', DiscountTypeEnum::CART_CHECKOUT)
            ->where('is_active', true)
            ->where(function ($q): void {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q): void {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->where(function ($q): void {
                $q->whereNull('usage_limit_total')
                    ->orWhereColumn('total_usage_count', '<', 'usage_limit_total');
            })
            ->where(function ($q) use ($appliedCouponCode): void {
                $q->where('requires_coupon', false);

                if ($appliedCouponCode) {
                    $q->orWhereHas('coupons', fn (Builder $cq) => $cq
                        ->where('code', $appliedCouponCode)
                        ->where('is_active', true)
                        ->where(function (Builder $usageQ): void {
                            $usageQ->whereNull('usage_limit')
                                ->orWhereColumn('usage_count', '<', 'usage_limit');
                        })
                    );
                }
            })
            ->with('rules')
            ->orderBy('priority', 'asc')
            ->when($appliedCouponCode, fn (Builder $query) => $query->orderBy('requires_coupon'));

        return $query->get();
    }

    // ──────────────────────────────────────────────
    //  Context building
    // ──────────────────────────────────────────────

    /**
     * Assembles the initial OrderContextData from the raw request data.
     */
    public function buildOrderContext(OrderCreateData $data, bool $useFreshData = false): OrderContextData
    {
        $customer = User::find($data->customer_id);
        $pdoIds   = collect($data->items)->pluck('product_delivery_option_id')->all();

        $deliveryOptions = ProductDeliveryOption::query()
            ->with([
                'product',
                'productDeliveryOptionDiscountPrice',
            ])
            ->findMany($pdoIds)
            ->keyBy('id');

        if (count($pdoIds) !== $deliveryOptions->count()) {
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
            $priceData         = $this->priceService->getPriceDataForOption($option);
            $startingPriceForCalc = $priceData->current_price;

            $initialLineItemTotal = $startingPriceForCalc * $itemData->qty_ordered;

            if ($itemData->payment_type === OrderItemPaymentTypeEnum::PRE_PAYMENT->value) {
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

    // ──────────────────────────────────────────────
    //  Condition checking
    // ──────────────────────────────────────────────

    /**
     * Check if all condition rules on a promotion pass for the given context.
     */
    public function promotionConditionsPass(DiscountPromotion $promotion, OrderContextData $context): bool
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
                return false;
            }
        }

        return true;
    }

    /**
     * Convenience: build context from OrderCreateData then check conditions.
     */
    public function checkPromotionConditions(DiscountPromotion $promotion, OrderCreateData $data): bool
    {
        $context = $this->buildOrderContext($data);

        return $this->promotionConditionsPass($promotion, $context);
    }
}
