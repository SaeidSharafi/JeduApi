<?php

declare(strict_types=1);

namespace App\Data\Shop\Order;

use App\Enums\Payment\PaymentMethodEnum;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class RetryOrderPaymentData extends Data
{
    public function __construct(
        public readonly PaymentMethodEnum $payment_method,
        public readonly ?int $amount,
    ) {}

    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'payment_method' => ['required', Rule::enum(PaymentMethodEnum::class)],
            'amount'         => ['nullable', 'integer', 'min:1000'], // Minimum 1000 IRR
        ];
    }

    /**
     * @codeCoverageIgnore
     */
    public static function bodyParameters(): array
    {
        return [
            'payment_method' => [
                'description' => 'The payment method to use for the retry',
                'example'     => 'mellat_gateway',
            ],
            'amount' => [
                'description' => 'Optional: Amount to pay (defaults to full balance_due)',
                'example'     => 500000,
            ],
        ];
    }
}
