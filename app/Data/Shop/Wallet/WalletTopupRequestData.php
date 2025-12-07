<?php

declare(strict_types=1);

namespace App\Data\Shop\Wallet;

use Spatie\LaravelData\Data;

final class WalletTopupRequestData extends Data
{
    public function __construct(
        public int $amount,
        public string $payment_method,
        public ?array $payment_data = null,
    ) {}

    public static function rules(): array
    {
        return [
            'amount'         => ['required', 'integer', 'min:10000'],
            'payment_method' => ['required', 'in:mellat_gateway,bank_transfer'],
            'payment_data'   => ['nullable', 'array'],
        ];
    }

    /** @codeCoverageIgnore */
    public static function bodyParameters(): array
    {
        return [
            'amount' => [
                'description' => 'The amount to add to the wallet (in Rials). Minimum: 10,000.',
                'example'     => 500000,
            ],
            'payment_method' => [
                'description' => 'The payment method to use for the top-up.',
                'example'     => 'mellat_gateway',
            ],
            'payment_data' => [
                'description' => 'Additional payment method data (e.g., bank transfer details).',
                'example'     => [
                    'transaction_id'   => 'TXN123456',
                    'sender_name'      => 'John Doe',
                    'transaction_date' => '2025-12-07',
                ],
            ],
        ];
    }
}
