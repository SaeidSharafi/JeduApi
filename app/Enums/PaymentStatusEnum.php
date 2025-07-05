<?php

namespace App\Enums;

use App\Traits\AdvanceEnum;

enum PaymentStatusEnum: string
{
    use AdvanceEnum;

    case PENDING = 'pending';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
}
