<?php

namespace App\Data\Order;

use App\Enums\OrderItemPaymentTypeEnum;
use App\Enums\OrderItemStatusEnum;
use App\Enums\OrderStatusEnum;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

class OrderItemCreateData extends Data
{
    public function __construct(
        public int $product_delivery_option_id,
        public string $payment_type,
        public int $discount_amount,
        public int $qty_ordered = 1,
        public int $tax_amount = 0,
    ) {
    }

    public static function rules(ValidationContext $context): array
    {
        return [
            'product_delivery_option_id' => ['required', 'integer', 'exists:product_delivery_options,id'],
            'payment_type'               => ['required', 'string', Rule::enum(OrderItemPaymentTypeEnum::class)],
            'discount_amount'            => ['required', 'integer', 'min:0'],
            'qty_ordered'                => ['nullable', 'integer', 'min:1'],
            'tax_amount'                 => ['nullable', 'integer', 'min:0']
        ];
    }
}
