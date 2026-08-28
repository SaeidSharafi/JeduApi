<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\AdvanceEnum;

enum ProvisioningReadinessEnum: string
{
    /** @use AdvanceEnum<value-of<self>> */
    use AdvanceEnum;

    case READY    = 'ready';
    case DISABLED = 'disabled';
    case INVALID  = 'invalid';
}
