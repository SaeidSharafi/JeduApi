<?php

declare(strict_types=1);

namespace App\Data\Admin\Order;

use App\Data\Admin\Discounts\OrderContextData;
use Spatie\LaravelData\Data;

final class OrderPreviewData extends Data
{
    public function __construct(
        public array $items,
        public int $subtotal_full_payment_items,
        public int $subtotal_all_items,
        public array $applied_cart_discounts = [],
        public ?string $triggered_by_coupon_code = null,
    ) {}

    public static function fromOrderContext(OrderContextData $data): self
    {
        $items = $data->items->map(function ($item) {
            return [
                'product_delivery_option' => [
                    'id'                => $item->product_delivery_option->id,
                    'name'              => $item->product_delivery_option->name,
                    'price'             => $item->product_delivery_option->price,
                    'discount_price'    => $item->product_delivery_option->discount_price,
                    'is_prepayment'     => $item->product_delivery_option->is_prepayment_available,
                    'prepayment_amount' => $item->product_delivery_option->prepayment_amount,
                ],
                'qty'                      => $item->qty,
                'payment_type'             => $item->payment_type->value,
                'price'                    => $item->price,
                'total'                    => $item->total,
                'discount_amount'          => $item->discount_amount,
                'applied_discount_details' => $item->applied_discount_details,

            ];
        });

        return new self(
            items: $items->all(),
            subtotal_full_payment_items: $data->subtotal_full_payment_items,
            subtotal_all_items: $data->subtotal_all_items,
            applied_cart_discounts: $data->applied_cart_discounts,
            triggered_by_coupon_code: $data->triggered_by_coupon_code,
        );
    }
}
