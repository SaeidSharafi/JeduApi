<?php

declare(strict_types=1);

namespace App\Data\Admin\Order;

use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Enums\Order\OrderStatusEnum;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class OrderCreateData extends Data
{
    public function __construct(
        public string $status,
        public int $customer_id,
        #[DataCollectionOf(OrderItemCreateData::class)]
        public array $items,
        public ?string $applied_coupon_code,
        public ?string $admin_notes,
    ) {}

    public static function rules(ValidationContext $context): array
    {
        return [
            'status'                             => ['required', 'string', Rule::enum(OrderStatusEnum::class)],
            'customer_id'                        => ['required', 'integer', 'exists:users,id'],
            'applied_coupon_code'                => ['nullable', 'string', 'max:255'],
            'admin_notes'                        => ['nullable', 'string', 'max:1000'],
            'items'                              => ['required', 'array', 'min:1'],
            'items.*.product_delivery_option_id' => ['required', 'integer', 'exists:product_delivery_options,id'],
            'items.*.payment_type'               => ['required', 'string', Rule::enum(OrderItemPaymentTypeEnum::class)],
            'items.*.discount_amount'            => ['required', 'integer', 'min:0'],
            'items.*.qty_ordered'                => ['nullable', 'integer', 'min:1'],
            'items.*.tax_amount'                 => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @codeCoverageIgnore
     *
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return [
            'status' => [
                'description' => 'Order status',
                'example'     => OrderStatusEnum::PENDING->value,
            ],
            'customer_id' => [
                'description' => 'ID of the customer placing the order',
                'example'     => 1,
            ],
            'applied_coupon_code' => [
                'description' => 'The coupon code applied to the order, if any',
                'example'     => 'SUMMER2023',
            ],
            'admin_notes' => [
                'description' => 'Notes for the admin regarding the order',
                'example'     => 'Please handle this order with priority.',
            ],
            'items' => [
                'description' => 'List of items in the order',
            ],
            'items.*.product_delivery_option_id' => [
                'description' => 'ID of the product delivery option for the item',
                'example'     => 1,
            ],
            'items.*.payment_type' => [
                'description' => 'Payment type for the item',
                'example'     => OrderItemPaymentTypeEnum::FULL_PAYMENT->value,
            ],
            'items.*.discount_amount' => [
                'description' => 'Discount amount applied to the item',
                'example'     => 0,
            ],
            'items.*.qty_ordered' => [
                'description' => 'Quantity of the item ordered',
                'example'     => 1,
            ],
            'items.*.tax_amount' => [
                'description' => 'Tax amount applied to the item',
                'example'     => 0,
            ],
        ];
    }
}
