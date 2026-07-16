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
    ) {}
}
