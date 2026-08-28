<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\AdvanceEnum;

enum ProvisioningTriggerEnum: string
{
    /** @use AdvanceEnum<value-of<self>> */
    use AdvanceEnum;

    case PAYMENT = 'payment';
    case RETRY   = 'retry';
    case MANUAL  = 'manual';
}
