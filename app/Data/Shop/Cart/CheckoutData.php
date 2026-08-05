<?php

declare(strict_types=1);

namespace App\Data\Shop\Cart;

use App\Services\Payment\GatewayService;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class CheckoutData extends Data
{
    public function __construct(
        public ?string $payment_method = null,
        public ?array $payment_data = null,
    ) {}

    /**
     * Define validation rules for the request.
     */
    public static function rules(?ValidationContext $context = null): array
    {
        $gateWayServices = app(GatewayService::class);

        return [
            'payment_method' => ['nullable', 'string', Rule::in($gateWayServices->getShopActiveGateways())],
            'payment_data'   => ['nullable', 'array'],
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
                'description' => 'The payment method to use for checkout. Optional for free orders (will auto-use NO_PAYMENT). Required for paid orders. Options: "wallet" for immediate payment, "bank_transfer" for pending order, or "mellat" for redirect to payment gateway.',
                'example'     => 'wallet',
            ],
            'payment_data' => [
                'description' => 'Optional payload passed to the payment processor (e.g. bank transfer details, gateway-specific data). Shape depends on the selected payment_method.',
                'example'     => ['notes' => 'Paid via app'],
            ],
        ];
    }
}
