<?php

declare(strict_types=1);

namespace App\Enums\Payment;

use App\Traits\AdvanceEnum;

enum PaymentStatusEnum: string
{
    use AdvanceEnum;

    case PENDING   = 'pending';
    case COMPLETED = 'completed';
    case FAILED    = 'failed';
}
