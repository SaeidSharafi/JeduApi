<?php

namespace App\Data\Payment;

use Spatie\LaravelData\Data;

class PaymentUpdateData extends Data
{
    public function __construct(
        public ?string $status = null,
        public ?string $admin_notes = null,
    )
    {
    }
}
