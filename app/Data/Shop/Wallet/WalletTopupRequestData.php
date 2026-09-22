<?php

declare(strict_types=1);

namespace App\Data\Shop\Wallet;

use App\Enums\Payment\PaymentPurposeEnum;
use App\Services\Payment\GatewayService;
use Illuminate\Validation\Rule;
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
            'payment_method' => ['required', 'string', Rule::in(self::eligiblePaymentMethods())],
            'payment_data'   => ['nullable', 'array'],
        ];
    }

    /**
     * Payment methods a wallet may be topped up with.
     *
     * Sourced from the gateway settings rather than a hard-coded list, so the
     * admin's `wallet_topup_enabled` toggle is the single gate: a disabled
     * gateway (or one that is top-up ineligible, such as the wallet itself and
     * bank transfer) never validates here. A gateway added to the top-up list
     * through settings needs no code change.
     *
     * @return array<int, string>
     */
    public static function eligiblePaymentMethods(): array
    {
        return app(GatewayService::class)
            ->getShopActiveGateways(PaymentPurposeEnum::WALLET_TOPUP);
    }

    /**
     * Reuse the gateway-specific rejection message for every ineligible method,
     * with the submitted method interpolated as its name.
     */
    public static function messages(): array
    {
        return [
            'payment_method.in' => __('messages.wallet.gateway_not_allowed_for_topup', [
                'gateway' => ':input',
            ]),
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
                'description' => 'The payment gateway to use for the transaction. Must be an active gateway with `wallet_topup_enabled`; gateways marked top-up ineligible (the wallet itself, bank transfer) are rejected.',
                'example'     => 'mellat_gateway',
            ],
            'payment_data' => [
                'description' => 'Additional data required by the chosen payment gateway.',
                'example'     => null,
            ],
        ];
    }
}
