<?php

namespace App\Data\Admin\Payment;

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
