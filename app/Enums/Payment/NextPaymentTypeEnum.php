<?php

declare(strict_types=1);

namespace App\Enums\Payment;

use App\Traits\AdvanceEnum;

enum NextPaymentTypeEnum: string
{
    use AdvanceEnum;
    case INITIAL_PAYMENT = 'initial_payment';
    case FINAL_BALANCE   = 'final_balance';
    case NONE            = 'none';

}
