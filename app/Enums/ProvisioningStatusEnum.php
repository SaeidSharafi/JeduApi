<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\AdvanceEnum;

enum ProvisioningStatusEnum: string
{
    /** @use AdvanceEnum<value-of<self>> */
    use AdvanceEnum;

    case READY                  = 'ready';
    case IN_PROGRESS            = 'in_progress';
    case HEALTHY                = 'healthy';
    case DEGRADED               = 'degraded';
    case MANUAL_ACTION_REQUIRED = 'manual_action_required';
}
