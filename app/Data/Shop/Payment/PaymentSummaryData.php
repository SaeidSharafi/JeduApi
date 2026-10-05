<?php

declare(strict_types=1);

namespace App\Data\Shop\Payment;

use Spatie\LaravelData\Data;

final class PaymentSummaryData extends Data
{
    public function __construct(
        public readonly ?int $id,
        public readonly ?string $uuid,
        public readonly ?int $amount,
        public readonly ?string $method,
        public readonly ?string $status,
        public readonly ?string $purpose,
        public readonly ?string $last_gateway_reference = null,
        public readonly int $attempt_count = 0,
    ) {}
}
