<?php

declare(strict_types=1);

namespace App\Enums\Wallet;

use App\Traits\AdvanceEnum;

enum TransactionTypeEnum: string
{
    use AdvanceEnum;

    case DEPOSIT    = 'deposit';
    case WITHDRAWAL = 'withdrawal';
    case PAYMENT    = 'payment';
    case REFUND     = 'refund';
    case GIFT       = 'gift';
    case BONUS      = 'bonus';
    case ADJUSTMENT = 'adjustment';
}
