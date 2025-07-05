<?php

namespace App\Enums;

use App\Traits\AdvanceEnum;

enum OrderPaymentStatusEnum: string
{
    use AdvanceEnum;

    case PENDING = 'pending'; // No payment initiated
    case PARTIALLY_PAID = 'partially_paid'; // Pre-payment made, balance outstanding
    case PAID = 'paid'; // Paid in full
    case REFUNDED = 'refunded';
    case PARTIALLY_REFUNDED = 'partially_refunded';
}
