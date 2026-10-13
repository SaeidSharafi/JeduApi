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
use App\Models\DiscountCoupon;
use App\Models\DiscountPromotion;
use App\Models\DiscountPromotionUsage;
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
     * A promotion whose total cap, or whose supplied code's own cap, is already
     * filled by reserved capacity is not found, so the caller can reject the
     * code instead of silently dropping the discount.
     *
     * When $lockForUpdate is true, this must run inside a DB transaction: the
     * promotion row is locked so concurrent checkouts serialize their
     * per-customer limit checks.
     */
    public function findPromotionByCoupon(string $couponCode, bool $lockForUpdate = false): ?DiscountPromotion
    {
        $promotion = DiscountPromotion::query()
            ->where('is_active', true)
            ->where(function ($q): void {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q): void {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->whereHas('coupons', fn (Builder $q) => $q
                ->where('code', $couponCode)
                ->where('is_active', true)
            )
            ->when($lockForUpdate, fn (Builder $query): Builder => $query->lockForUpdate())
            ->with('rules')
            ->first();

        if ($promotion === null) {
            return null;
        }

        return $this->filterFullyReserved(collect([$promotion]), $couponCode)->first();
    }

    /**
     * Find ALL applicable CART_CHECKOUT promotions for stacking.
     * Returns promotions sorted by priority ASC.
     *
     * A promotion with requires_coupon = true needs a matching coupon to activate.
     * A promotion with requires_coupon = false is always evaluated subject to its conditions.
     * A promotion whose total cap, whose applied code's cap, or whose per-customer
     * allowance is filled by reservations is dropped entirely (never an error):
     * the sale proceeds without that discount.
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
            ->where(function ($q) use ($appliedCouponCode): void {
                $q->where('requires_coupon', false);

                if ($appliedCouponCode) {
                    $q->orWhereHas('coupons', fn (Builder $cq) => $cq
                        ->where('code', $appliedCouponCode)
                        ->where('is_active', true)
                    );
                }
            })
            ->with('rules')
            ->orderBy('priority', 'asc');

        $promotions = $this->filterFullyReserved($query->get(), $appliedCouponCode);

        return $this->perCustomerEligibility->filterExhausted($promotions, $customerId);
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

        $subtotal_all_items                    = 0;
        $subtotal_full_payment_items           = 0;
        $effective_subtotal_all_items          = 0;
        $effective_subtotal_discountable_items = 0;
        $calculatedItems                       = collect();
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

            // The threshold snapshot: the same line set `discountableItems()`
            // exposes to every markdown action, at its product-effective worth.
            if (! $calculatedItem->is_bundle
                && $calculatedItem->payment_type !== OrderItemPaymentTypeEnum::PRE_PAYMENT
            ) {
                $effective_subtotal_discountable_items += $calculatedItem->total;
            }
        }

        return new OrderContextData(
            customer: $customer,
            items: $calculatedItems,
            subtotal_full_payment_items: $subtotal_full_payment_items,
            subtotal_all_items: $subtotal_all_items,
            effective_subtotal_all_items: $effective_subtotal_all_items,
            effective_subtotal_discountable_items: $effective_subtotal_discountable_items,
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

    /**
     * Drop promotions whose total usage cap, or whose supplied coupon's own
     * usage cap, is already filled by reserved capacity.
     *
     * Caps are budget targets counted from active reservation rows — a
     * placed-but-unsettled order holds its slot, so a promotion or code leaves
     * the calculation as soon as reservations reach the cap. The completion
     * counters are the reporting metric, not the cap decision. Concurrent
     * checkouts may overshoot a cap by their own number, because the count is
     * not serialized (spec #150, decision a).
     *
     * @param  Collection<int, DiscountPromotion>  $promotions
     * @return Collection<int, DiscountPromotion>
     */
    private function filterFullyReserved(Collection $promotions, ?string $appliedCouponCode = null): Collection
    {
        if ($promotions->isEmpty()) {
            return $promotions;
        }

        $promotionIds = $promotions->pluck('id')->all();

        $reservationRows = DiscountPromotionUsage::query()
            ->active()
            ->whereIn('discount_promotion_id', $promotionIds)
            ->selectRaw('discount_promotion_id, discount_coupon_id, count(*) as reserved_usage_count')
            ->groupBy('discount_promotion_id', 'discount_coupon_id')
            ->get();

        $appliedCoupons = $appliedCouponCode === null
            ? collect()
            : DiscountCoupon::query()
                ->whereIn('discount_promotion_id', $promotionIds)
                ->where('code', $appliedCouponCode)
                ->where('is_active', true)
                ->get()
                ->keyBy('discount_promotion_id');

        return $promotions
            ->reject(function (DiscountPromotion $promotion) use ($appliedCoupons, $reservationRows): bool {
                if ($this->isTotalCapFullyReserved($promotion, $reservationRows)) {
                    return true;
                }

                $coupon = $appliedCoupons->get($promotion->id);

                return $coupon !== null && $this->isCouponCapFullyReserved($coupon, $reservationRows);
            })
            ->values();
    }

    /**
     * @param  Collection<int, DiscountPromotionUsage>  $reservationRows
     */
    private function isTotalCapFullyReserved(DiscountPromotion $promotion, Collection $reservationRows): bool
    {
        if ($promotion->usage_limit_total === null) {
            return false;
        }

        $reservedCount = $reservationRows
            ->where('discount_promotion_id', $promotion->id)
            ->sum('reserved_usage_count');

        return (int) $reservedCount >= $promotion->usage_limit_total;
    }

    /**
     * @param  Collection<int, DiscountPromotionUsage>  $reservationRows
     */
    private function isCouponCapFullyReserved(DiscountCoupon $coupon, Collection $reservationRows): bool
    {
        if ($coupon->usage_limit === null) {
            return false;
        }

        $reservedCount = $reservationRows
            ->where('discount_promotion_id', $coupon->discount_promotion_id)
            ->where('discount_coupon_id', $coupon->id)
            ->sum('reserved_usage_count');

        return (int) $reservedCount >= $coupon->usage_limit;
    }
}
