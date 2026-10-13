<?php

declare(strict_types=1);

namespace App\Data\Admin\Discounts;

use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Models\ProductDeliveryOption;
use Spatie\LaravelData\Data;

final class CalculatedOrderItemData extends Data
{
    public function __construct(
        public ProductDeliveryOption $product_delivery_option,
        public int $qty,
        public OrderItemPaymentTypeEnum $payment_type,
        public int $price, // The original unit price for this item
        public int $total,
        public int $discount_amount = 0, // Starts at 0, increased by actions
        /**
         * A running log of discounts applied to this specific item.
         * This will be serialized into the order_items.applied_discount_details_json column.
         *
         * @var array
         */
        public array $applied_discount_details = [],
        public bool $is_gift = false,
        /**
         * True when this line is a Bundle PDO (composite SKU). Bundle lines
         * are priced at their reviewed selling price and must never receive a
         * coupon or cart-promotion markdown; unrelated standalone lines in the
         * same cart keep their otherwise eligible discounts.
         */
        public bool $is_bundle = false,
    ) {}

    /**
     * Apply a markdown to this line and return the amount actually applied.
     * Bundle lines and prepayment (deposit) lines are never marked down, so a
     * mis-filtered handler cannot mark a deposit down.
     */
    public function applyDiscount(int $amount): int
    {
        if ($this->is_bundle || $this->payment_type === OrderItemPaymentTypeEnum::PRE_PAYMENT) {
            return 0;
        }

        $applied = max(0, min($amount, $this->total));

        $this->discount_amount += $applied;
        $this->total -= $applied;

        return $applied;
    }
}
