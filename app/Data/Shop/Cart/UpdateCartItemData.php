<?php

declare(strict_types=1);

namespace App\Data\Shop\Cart;

use App\Enums\Order\OrderItemPaymentTypeEnum;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class UpdateCartItemData extends Data
{
    public function __construct(
        public int $quantity,
        public OrderItemPaymentTypeEnum $payment_type,
    ) {}

    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'payment_type' => ['required', Rule::enum(OrderItemPaymentTypeEnum::class),],
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
            'quantity' => [
                'description' => 'The new quantity for the cart item',
                'example'     => 2,
            ],
            'payment_type' => [
                'description' => 'The payment type for the cart item',
                'example'     => 'full_payment',
            ],
        ];
    }
}
