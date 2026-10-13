<?php

declare(strict_types=1);

namespace App\Data\Admin\Discounts;

use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Models\DiscountPromotion;
use App\Models\User;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

final class OrderContextData extends Data
{
    public function __construct(
        public ?User $customer,

        /** @var DataCollection<CalculatedOrderItemData> */
        #[DataCollectionOf(CalculatedOrderItemData::class)]
        public Collection $items,

        /**
         * The subtotal of ONLY full_payment items.
         * Used by conditions that must exclude prepayments.
         */
        public int $subtotal_full_payment_items,

        /**
         * The true total value of all items in the cart (full + prepayment).
         * Used by conditions that need to evaluate the entire commitment.
         */
        public int $subtotal_all_items,

        /**
         * A running log of cart-wide discounts applied (e.g., from a coupon).
         * This will be serialized into the orders.applied_cart_discounts_json column.
         *
         * @var array
         */
        public array $applied_cart_discounts = [],

        /**
         * This holds a reference to the promotion being evaluated.
         * It allows action handlers to access details like the promotion's name for the audit trail.
         */
        public ?DiscountPromotion $evaluating_promotion = null,
        public ?string $triggered_by_coupon_code = null,
        public ?int $order_id = null,

        /**
         * The sum of every line's total at context-build time: product-level
         * discounts already applied, prepayment lines counted at their deposit
         * amount, bundle lines at their effective price.
         *
         * This is a snapshot and is never mutated by cart actions. It is the
         * all-lines value a threshold is measured against, so the prepayment
         * setting only selects a line set and never a price basis.
         */
        public int $effective_subtotal_all_items = 0,
    ) {}

    /**
     * The single amount a value-based condition measures against.
     *
     * Both options use the same effective (product-discount-applied) basis;
     * the flag only selects the line set: the customer's whole commitment
     * (deposit lines counted at their paid deposit) or only the lines being
     * paid in full.
     */
    public function thresholdBasis(bool $measureWholeCommitment): int
    {
        return $measureWholeCommitment
            ? $this->effective_subtotal_all_items
            : $this->subtotal_full_payment_items;
    }

    /**
     * Calculate the grand total (what the customer must pay).
     * This is the SINGLE SOURCE OF TRUTH for the order's billable amount.
     *
     * For pre_payment items: total = prepayment_amount * qty
     * For full_payment items: total = (price - discount) * qty
     *
     * @return int The final amount to be billed
     */
    public function calculateGrandTotal(): int
    {
        return $this->items->sum('total');
    }

    /**
     * Calculate the subtotal (sum of all item prices before discounts).
     * This represents the original value before any discounts are applied.
     *
     * @return int The sum of (price * quantity) for all items
     */
    public function calculateSubtotal(): int
    {
        return $this->items->sum(fn (CalculatedOrderItemData $item): int => $item->price * $item->qty);
    }

    /**
     * Calculate the total discount amount applied across all items.
     *
     * @return int The sum of all discount_amount fields
     */
    public function calculateTotalDiscount(): int
    {
        return $this->items->sum('discount_amount');
    }

    /**
     * Every line a cart promotion may mark down: no bundles, no deposits.
     *
     * @return Collection<int, CalculatedOrderItemData>
     */
    public function discountableItems(): Collection
    {
        return $this->items->reject(
            fn (CalculatedOrderItemData $item): bool => $item->is_bundle
                || $item->payment_type === OrderItemPaymentTypeEnum::PRE_PAYMENT
        )->values();
    }

    /**
     * Product-discount-applied current worth of those lines.
     */
    public function discountableTotal(): int
    {
        return $this->discountableItems()->sum('total');
    }
}
