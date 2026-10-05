<?php

declare(strict_types=1);

namespace App\Data\Shop\Payment;

use Spatie\LaravelData\Data;

final class PaymentResponseData extends Data
{
    public function __construct(
        public readonly string $status,
        public readonly ?string $order_id = null,
        public readonly ?string $payment_id = null,
        public readonly ?string $error_code = null,
        public readonly bool $can_retry = false,
    ) {}
}
