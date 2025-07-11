<?php

declare(strict_types=1);

namespace App\Data\Admin\Payment;

use Spatie\LaravelData\Data;

final class PaymentUpdateData extends Data
{
    public function __construct(
        public ?string $status = null,
        public ?string $admin_notes = null,
    ) {}
}
