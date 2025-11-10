<?php

declare(strict_types=1);

namespace App\Data\Shop\Cart;

use App\Enums\Payment\PaymentMethodEnum;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class CheckoutData extends Data
{
    public function __construct(
        public ?string $payment_method = null,
    ) {}

    /**
     * Define validation rules for the request.
     */
    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'payment_method' => ['nullable', 'string', Rule::enum(PaymentMethodEnum::class)],
        ];
    }

    /**
     * Define body parameters for Scribe API documentation.
     *
     * @codeCoverageIgnore
     */
    public static function bodyParameters(): array
    {
        return [
            'payment_method' => [
                'description' => 'The payment method to use for checkout. Optional for free orders (will auto-use NO_PAYMENT). Required for paid orders. Options: "wallet" for immediate payment, "bank_transfer" for pending order, or "online_gateway" for redirect to payment gateway.',
                'example'     => 'wallet',
            ],
        ];
    }
}
