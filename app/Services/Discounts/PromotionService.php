<?php

declare(strict_types=1);

namespace App\Services\Discounts;

use App\Actions\Admin\Discounts\ResolvePerCustomerPromotionEligibilityAction;
use App\Contracts\Discounts\DiscountConditionContract;
use App\Data\Admin\Discounts\CalculatedOrderItemData;
use App\Data\Admin\Discounts\OrderContextData;
use App\Data\Admin\Order\OrderCreateData;
use App\Enums\Order\DiscountTypeEnum;
use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Enums\Product\ProductableEnum;
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
        private ResolvePerCustomerPromotionEligibilityAction $perCustomerEligibility,
    ) {}

    // ──────────────────────────────────────────────
    //  Finding
    // ──────────────────────────────────────────────

    /**
     * Find a single active promotion by its coupon code.
     * Used by CartService to locate a promotion when a coupon is entered.
     *
     * When $lockForUpdate is true, this must run inside a DB transaction: the
     * promotion row is locked so concurrent checkouts serialize their
     * per-customer limit checks.
     */
    public function findPromotionByCoupon(string $couponCode, bool $lockForUpdate = false): ?DiscountPromotion
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
            ->when($lockForUpdate, fn (Builder $query): Builder => $query->lockForUpdate())
            ->with('rules')
            ->first();
    }

    /**
     * Find ALL applicable CART_CHECKOUT promotions for stacking.
     * Returns promotions sorted by priority ASC.
     *
     * A promotion with requires_coupon = true needs a matching coupon to activate.
     * A promotion with requires_coupon = false is always evaluated subject to its conditions.
     * A promotion whose per-customer allowance the customer has used up is dropped
     * entirely (never an error): the sale proceeds without that discount.
     *
     * @return Collection<int, DiscountPromotion>
     */
    public function findAllApplicableCartPromotions(
        ?string $appliedCouponCode = null,
        ?int $customerId = null,
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
            ->orderBy('priority', 'asc');

        return $this->perCustomerEligibility->filterExhausted($query->get(), $customerId);
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

        $subtotal_all_items           = 0;
        $subtotal_full_payment_items  = 0;
        $effective_subtotal_all_items = 0;
        $calculatedItems              = collect();
        foreach ($data->items as $itemData) {
            /** @var ProductDeliveryOption $option */
            $option = $deliveryOptions->get($itemData->product_delivery_option_id);

            $originalFullPrice    = $option->price;
            $priceData            = $this->priceService->getPriceDataForOption($option);
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
                is_bundle: $option->product?->productable_type === ProductableEnum::BUNDLE->value,
            );
            $calculatedItems->push($calculatedItem);

            $subtotal_all_items += $originalFullPrice * $calculatedItem->qty;
            // A line's total is already its product-effective value: the
            // discounted price for a full-payment line and the deposit for a
            // prepayment line.
            $effective_subtotal_all_items += $calculatedItem->total;

            if ($calculatedItem->payment_type === OrderItemPaymentTypeEnum::FULL_PAYMENT) {
                $subtotal_full_payment_items += $startingPriceForCalc * $calculatedItem->qty;
            }
        }

        return new OrderContextData(
            customer: $customer,
            items: $calculatedItems,
            subtotal_full_payment_items: $subtotal_full_payment_items,
            subtotal_all_items: $subtotal_all_items,
            effective_subtotal_all_items: $effective_subtotal_all_items,
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
        // A selection is only ever valid for the promotion whose conditions just
        // ran: clearing here keeps publish and consume adjacent within one
        // promotion, so a selection cannot survive a failed sibling condition or
        // leak into a later promotion that has the consuming action but no tier
        // condition. A missing selection must produce no markdown rather than a
        // foreign percentage.
        $context->condition_selections = [];

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
}
