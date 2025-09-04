<?php

declare(strict_types=1);

namespace App\Enums\Wallet;

use App\Traits\AdvanceEnum;

enum TransactionSourceEnum: string
{
    use AdvanceEnum;

    case ORDER     = 'order';
    case STAFF     = 'staff';
    case PROMOTION = 'promotion';
    case CAMPAIGN  = 'campaign';
    case SYSTEM    = 'system';
}
