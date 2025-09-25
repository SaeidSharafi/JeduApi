<?php

declare(strict_types=1);

namespace App\Data\Admin\Order;

use App\Enums\Order\OrderItemPaymentTypeEnum;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class OrderItemCreateData extends Data
{
    public function __construct(
        public int $product_delivery_option_id,
        public string $payment_type,
        public int $qty_ordered = 1,
    ) {}

    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'product_delivery_option_id' => ['required', 'integer', 'exists:product_delivery_options,id'],
            'payment_type'               => ['required', 'string', Rule::enum(OrderItemPaymentTypeEnum::class)],
            'qty_ordered'                => ['nullable', 'integer', 'min:1'],
        ];
    }
}
