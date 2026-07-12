<?php

declare(strict_types=1);

namespace App\Data\Shop\Wallet;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class WalletTopupRequestData extends Data
{
    public function __construct(
        public readonly int $amount,
        public readonly string $payment_method,
        public readonly ?array $payment_data = null,
    ) {}

    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'amount'         => ['required', 'integer', 'min:10000'],
            'payment_method' => ['required', 'string', 'in:mellat_gateway,digipay'],
            'payment_data'   => ['nullable', 'array'],
        ];
    }

    /**
     * @codeCoverageIgnore
     */
    public static function bodyParameters(): array
    {
        return [
            'amount' => [
                'description' => 'The top-up amount in Iranian Rial (minimum 10,000).',
                'example'     => 500000,
            ],
            'payment_method' => [
                'description' => 'The payment gateway to use for the transaction.',
                'example'     => 'mellat_gateway',
            ],
            'payment_data' => [
                'description' => 'Additional data required by the chosen payment gateway.',
                'example'     => null,
            ],
        ];
    }
}
