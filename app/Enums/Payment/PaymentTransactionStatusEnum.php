<?php

declare(strict_types=1);

namespace App\Enums\Payment;

use App\Traits\AdvanceEnum;

enum PaymentTransactionStatusEnum: string
{
    /** @use AdvanceEnum<value-of<self>> */
    use AdvanceEnum;

    case INITIATED = 'initiated';
    case COMPLETED = 'completed';
    case FAILED    = 'failed';
}
