<?php

declare(strict_types=1);

namespace App\Data\Shop\Payment;

use Spatie\LaravelData\Data;

/**
 * Request DTO for the gateway callback endpoint.
 *
 * Gateways POST/GET back with gateway-specific fields (e.g. Mellat sends
 * RefId/ResCode/SaleReferenceId; Digipay sends trackingCode/amount). The
 * payment UUID is bound from the URL ({payment:uuid}), not the body, so this
 * DTO intentionally accepts any gateway response shape via a permissive array.
 */
final class GatewayCallbackData extends Data
{
    public function __construct(
        public readonly ?array $gateway_response = null,
    ) {}

    /**
     * Permissive rules: gateway callbacks are gateway-specific and may arrive
     * via GET or POST with arbitrary fields.
     *
     * @codeCoverageIgnore
     *
     * @return array<string, array<string, mixed>>
     */
    public static function rules(): array
    {
        return [
            'gateway_response' => ['nullable', 'array'],
        ];
    }

    /**
     * @codeCoverageIgnore
     */
    public static function bodyParameters(): array
    {
        return [
            'gateway_response' => [
                'description' => 'The raw response payload from the payment gateway. Shape is gateway-specific (e.g. Mellat: RefId, ResCode, SaleOrderId, SaleReferenceId; Digipay: trackingCode, amount, result).',
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
