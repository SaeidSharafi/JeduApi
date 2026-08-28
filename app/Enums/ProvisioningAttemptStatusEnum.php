<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\AdvanceEnum;

enum ProvisioningAttemptStatusEnum: string
{
    /** @use AdvanceEnum<value-of<self>> */
    use AdvanceEnum;

    case QUEUED                 = 'queued';
    case RUNNING                = 'running';
    case SUCCEEDED              = 'succeeded';
    case RETRY_SCHEDULED        = 'retry_scheduled';
    case FAILED                 = 'failed';
    case MANUAL_ACTION_REQUIRED = 'manual_action_required';
}
