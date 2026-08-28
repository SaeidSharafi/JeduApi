<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\AdvanceEnum;

enum EnrollmentStatusEnum: string
{
    /** @use AdvanceEnum<value-of<self>> */
    use AdvanceEnum;
    case AWAITING_PAYMENT = 'awaiting_payment';            // Order created, awaiting payment
    case ACTIVE           = 'active';                      // User has access
    case SUSPENDED        = 'suspended';                   // Temp access block by admin/system
    case EXPIRED          = 'expired';                     // Access period has ended
    case CANCELLED        = 'cancelled';                   // Access permanently revoked (e.g., refund)

    /**
     * @codeCoverageIgnore
     */
    /**
     * @return array<int, self>
     */
    public static function occupyingStatuses(): array
    {
        return [
            self::ACTIVE,
            self::SUSPENDED,
        ];
    }

    /**
     * @codeCoverageIgnore
     */
    /**
     * @return array<int, self>
     */
    public static function nonOccupyingStatuses(): array
    {
        return [
            self::AWAITING_PAYMENT,
            self::CANCELLED,
            self::EXPIRED,
        ];
    }
}
