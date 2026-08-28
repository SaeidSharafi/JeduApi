<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\AdvanceEnum;

/**
 * Canonical provider outcome stored on the enrollment snapshot
 * (provisioning_data.providers.<name>.status).
 */
enum ProvisioningOutcomeStatusEnum: string
{
    /** @use AdvanceEnum<value-of<self>> */
    use AdvanceEnum;

    case SUCCESS                = 'success';
    case FAILED                 = 'failed';
    case MANUAL_ACTION_REQUIRED = 'manual_action_required';
    case WAIVED                 = 'waived';
}
