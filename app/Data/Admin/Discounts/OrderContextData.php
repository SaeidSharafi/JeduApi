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
         * A running log of the rewards cart promotions intend to grant the
         * customer (wallet and gift credits). Pricing must stay side-effect
         * free, so reward actions only append here; the list is snapshotted
         * onto orders.reward_intents_json and executed once the order completes.
         *
         * @var list<array<string, mixed>>
         */
        public array $reward_intents = [],

        /**
         * This holds a reference to the promotion being evaluated.
         * It allows action handlers to access details like the promotion's name for the audit trail.
         */
        public ?DiscountPromotion $evaluating_promotion = null,

        /**
         * The applied coupon code this calculation consumed, or null when no
         * coupon-required promotion used it. It accumulates across stacked
         * promotions — while a promotion's actions run it holds that
         * promotion's own attribution, so an automatic promotion never inherits
         * the code in the audit trail, but a later automatic promotion cannot
         * erase a code an earlier promotion consumed.
         */
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

        /**
         * The product-effective worth of exactly the lines a markdown may land
         * on (no bundles, no deposit lines), captured at context-build time.
         *
         * This is the full-payment side of {@see self::thresholdBasis()}: a
         * snapshot, so no later cart action — a gift line in particular — can
         * change what a threshold measures, and it matches the line set
         * `discountableItems()` exposes to every markdown action.
         */
        public int $effective_subtotal_discountable_items = 0,

        /**
         * Selections published by condition handlers: eligibility decisions
         * only, never price or total mutations. Keyed by the publishing
         * condition's handler key, so a consuming action shares one read/write
         * shape with the condition that produced the decision.
         *
         * @var array<string, Data>
         */
        public array $condition_selections = [],
    ) {}

    /**
     * Publish a condition's eligibility selection for its consuming action.
     */
    public function publishSelection(string $handlerKey, Data $selection): void
    {
        $this->condition_selections[$handlerKey] = $selection;
    }

    /**
     * Read a selection previously published by a condition handler.
     */
    public function publishedSelection(string $handlerKey): ?Data
    {
        return $this->condition_selections[$handlerKey] ?? null;
    }

    /**
     * The single amount a value-based condition measures against.
     *
     * Both sides are context-build snapshots, so neither can be changed by a
     * cart action that runs earlier in the same calculation: the whole
     * commitment (`effective_subtotal_all_items`, deposit lines at their paid
     * deposit) or the discountable line set
     * (`effective_subtotal_discountable_items`, the same set every markdown
     * action iterates). The flag only selects the line set, never a price
     * basis.
     *
     * The legacy `subtotal_full_payment_items` field is deliberately not used
     * as a measurement any more. `GiftProductAction` bumps it (and
     * `subtotal_all_items`) by the gift's list price for the full-value audit
     * trail, so a gift line is worth 0 to the customer while its list price
     * would otherwise inflate the full-payment-only measurement of a later
     * promotion in the same calculation.
     */
    public function thresholdBasis(bool $measureWholeCommitment): int
    {
        return $measureWholeCommitment
            ? $this->effective_subtotal_all_items
            : $this->effective_subtotal_discountable_items;
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
            fn (CalculatedOrderItemData $item): bool => ! $item->isDiscountable()
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
