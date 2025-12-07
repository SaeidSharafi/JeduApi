<?php

declare(strict_types=1);

namespace App\Data\Shop\Payment;

use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

final class GatewayCallbackData extends Data
{
    public function __construct(
        #[Required]
        public readonly string $transaction_refrence,

        #[Required]
        public readonly array $gateway_response,
    ) {}

    /**
     * @codeCoverageIgnore
     */
    public static function bodyParameters(): array
    {
        return [
            'payment_uuid' => [
                'description' => 'The UUID of the payment being verified.',
                'example'     => '550e8400-e29b-41d4-a716-446655440000',
            ],
            'gateway_response' => [
                'description' => 'The response data from the payment gateway.',
                'example'     => [
                    'RefId'           => '123456789',
                    'ResCode'         => '0',
                    'SaleOrderId'     => '12345',
                    'SaleReferenceId' => '987654321',
                ],
            ],
        ];
    }
}
