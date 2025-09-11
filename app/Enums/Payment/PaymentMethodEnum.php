<?php

declare(strict_types=1);

namespace App\Enums\Payment;

use App\Traits\AdvanceEnum;

enum PaymentMethodEnum: string
{
    use AdvanceEnum;

    case BANK_TRANSFER    = 'bank_transfer';
    case ONLINE_GATEWAY   = 'online_gateway';
    case CASH_ON_DELIVERY = 'cash_on_delivery';
    case WALLET           = 'wallet';
    case NO_PAYMENT       = 'no_payment';
}
