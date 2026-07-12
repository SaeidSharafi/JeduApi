<?php

declare(strict_types=1);

namespace App\Enums\Payment;

use App\Traits\AdvanceEnum;

enum PaymentPurposeEnum: string
{
    use AdvanceEnum;

    case ORDER        = 'order';
    case WALLET_TOPUP = 'wallet_topup';
}
