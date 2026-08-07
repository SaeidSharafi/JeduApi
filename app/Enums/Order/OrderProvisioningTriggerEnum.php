<?php

declare(strict_types=1);

namespace App\Enums\Order;

use App\Traits\AdvanceEnum;

enum OrderProvisioningTriggerEnum: string
{
    /** @use AdvanceEnum<value-of<self>> */
    use AdvanceEnum;

    case ANY_PAYMENT     = 'any_payment';
    case FULL_PAYMENT    = 'full_payment';
    case MANUAL_APPROVAL = 'manual_approval';
}
