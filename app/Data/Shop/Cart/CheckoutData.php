<?php

declare(strict_types=1);

namespace App\Data\Shop\Cart;

use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class CheckoutData extends Data
{
    public function __construct(
        #[Required]
        #[In(['wallet', 'bank_transfer'])]
        public string $payment_method,
    ) {}

    /**
     * Define validation rules for the request.
     */
    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'payment_method' => ['required', 'string', 'in:wallet,bank_transfer'],
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
                'description' => 'The payment method to use for checkout. Use "wallet" for immediate payment from user wallet balance, or "bank_transfer" to create a pending order awaiting bank transfer.',
                'example'     => 'wallet',
            ],
        ];
    }
}
