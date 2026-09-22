<?php

declare(strict_types=1);

namespace App\Data\Shop\Payment;

use App\Enums\Payment\PaymentPurposeEnum;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

/**
 * Query parameters for the shop-facing payment gateway list.
 *
 * The frontend asks for the list it needs by purpose: `order` (default) returns
 * gateways offered at checkout, `wallet_topup` returns gateways allowed to fund
 * a wallet. The two lists differ, so the caller must never assume one covers both.
 */
final class GatewayListRequestData extends Data
{
    public function __construct(
        public ?PaymentPurposeEnum $purpose = null,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'purpose' => ['nullable', Rule::enum(PaymentPurposeEnum::class)],
        ];
    }

    /**
     * @codeCoverageIgnore
     *
     * @return array<string, array<string, string>>
     */
    public function queryParameters(): array
    {
        return [
            'purpose' => [
                'description' => 'Which gateway list to return. `order` (default) lists gateways offered at checkout; `wallet_topup` lists gateways allowed to top up a wallet.',
                'example'     => 'wallet_topup',
            ],
        ];
    }
}
