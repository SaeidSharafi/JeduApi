<?php

namespace App\Enums\Payment;

use App\Traits\AdvanceEnum;

enum PaymentTypeEnum: string
{
    use AdvanceEnum;

    case ORDER = 'order';
    case WALLET_TOPUP = 'wallet_topup';
}
