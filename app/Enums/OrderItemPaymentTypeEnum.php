<?php

namespace App\Enums;

use App\Traits\AdvanceEnum;

enum OrderItemPaymentTypeEnum: string
{
    use AdvanceEnum;

    case PRE_PAYMENT = 'pre_payment';
    case FULL_PAYMENT = 'full_payment';
}
