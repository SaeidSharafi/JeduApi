<?php

namespace App\Data\Payment;

use Spatie\LaravelData\Data;

class PaymentCreateData extends Data
{
    public function __construct(
        public string $method,
        public string $status = 'pending',
        public ?string $admin_notes = null,

    )
    {
    }
}
