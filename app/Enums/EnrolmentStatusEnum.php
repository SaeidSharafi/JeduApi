<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\AdvanceEnum;

enum EnrolmentStatusEnum: string
{
    use AdvanceEnum;
    case PENDING_PROVISIONING = 'pending_provisioning'; // Order paid, access being set up
    case ACTIVE               = 'active';                       // User has access
    case SUSPENDED            = 'suspended';                   // Temp access block by admin/system
    case EXPIRED              = 'expired';                     // Access period has ended
    case CANCELLED            = 'cancelled';                   // Access permanently revoked (e.g., refund)
    case PROVISIONING_FAILED  = 'provisioning_failed';
}
