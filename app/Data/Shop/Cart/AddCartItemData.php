<?php

declare(strict_types=1);

namespace App\Data\Shop\Cart;

use App\Enums\Order\OrderItemPaymentTypeEnum;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class AddCartItemData extends Data
{
    public function __construct(
        public string $product_delivery_option_uuid,
        public OrderItemPaymentTypeEnum $payment_type = OrderItemPaymentTypeEnum::FULL_PAYMENT,
        public int $quantity = 1,
    ) {}

    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'product_delivery_option_uuid' => [
                'required',
                'string',
                'uuid',
                Rule::exists('product_delivery_options', 'uuid'),
            ],
            'payment_type' => [
                'nullable', Rule::enum(OrderItemPaymentTypeEnum::class),
            ],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:100'],
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
            'product_delivery_option_uuid' => [
                'description' => 'The UUID of the product delivery option to add to cart',
                'example'     => '01932e8f-4c3d-7b4e-9f3a-8c5e2d1b4a6f',
            ],
            'payment_type' => [
                'description' => 'The payment type for the cart item',
                'example'     => 'full_payment',
            ],
            'quantity' => [
                'description' => 'The quantity to add, defaults to 1',
                'example'     => 1,
            ],
        ];
    }
}
