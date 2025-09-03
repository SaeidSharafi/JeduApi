<?php

declare(strict_types=1);

namespace App\Enums\Wallet;

use App\Traits\AdvanceEnum;

enum WalletStatusEnum: string
{
    use AdvanceEnum;

    case ACTIVE    = 'active';
    case SUSPENDED = 'suspended';
    case CLOSED    = 'closed';
}
