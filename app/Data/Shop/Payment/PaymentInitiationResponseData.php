<?php

declare(strict_types=1);

namespace App\Data\Shop\Payment;

use Spatie\LaravelData\Data;

final class PaymentInitiationResponseData extends Data
{
    /** @param array<string, mixed>|null $redirect_data */
    public function __construct(
        public readonly ?PaymentSummaryData $payment,
        public readonly bool $requires_redirect = false,
        public readonly ?string $redirect_url = null,
        public readonly ?array $redirect_data = null,
        public readonly string $redirect_method = 'GET',
    ) {}
}
