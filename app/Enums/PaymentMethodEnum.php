<?php

namespace App\Enums;

use App\Traits\AdvanceEnum;

enum PaymentMethodEnum: string
{
    use AdvanceEnum;

    case BANK_TRANSFER = 'bank_transfer';
    case ONLINE_GATEWAY = 'online_gateway';
}
