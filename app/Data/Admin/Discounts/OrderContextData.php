<?php

declare(strict_types=1);

namespace App\Data\Admin\Discounts;

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
    ) {}

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
}
