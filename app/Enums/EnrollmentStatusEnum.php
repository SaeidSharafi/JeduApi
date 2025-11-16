<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\AdvanceEnum;

enum EnrollmentStatusEnum: string
{
    use AdvanceEnum;
    case AWAITING_PAYMENT     = 'awaiting_payment';            // Order created, awaiting payment
    case PENDING_PROVISIONING = 'pending_provisioning';        // Order paid, access being set up
    case ACTIVE               = 'active';                      // User has access
    case SUSPENDED            = 'suspended';                   // Temp access block by admin/system
    case EXPIRED              = 'expired';                     // Access period has ended
    case CANCELLED            = 'cancelled';                   // Access permanently revoked (e.g., refund)
    case PROVISIONING_FAILED  = 'provisioning_failed';


    public static function occupyingStatuses(): array
    {
        return [
            self::ACTIVE,
            self::PENDING_PROVISIONING,
            self::SUSPENDED,
        ];
    }

    public static function nonOccupyingStatuses(): array
    {
        return [
            self::AWAITING_PAYMENT,
            self::CANCELLED,
            self::EXPIRED,
            self::PROVISIONING_FAILED,
        ];
    }
}
