<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\AdvanceEnum;

/**
 * Lifecycle of an external provider revocation for one Enrollment.
 *
 * A null value means no revocation was ever required — the enrollment was
 * never refunded. `revoked` is terminal and monotonic: a successful revocation
 * is never undone because another provider failed, so only a state machine that
 * never leaves `revoked` is valid.
 */
enum EnrollmentRevocationStatusEnum: string
{
    /** @use AdvanceEnum<value-of<self>> */
    use AdvanceEnum;

    case PENDING                = 'pending';
    case FAILED                 = 'failed';
    case MANUAL_ACTION_REQUIRED = 'manual_action_required';
    case REVOKED                = 'revoked';

    /**
     * Revocation states that must keep Purchase Eligibility blocked.
     *
     * @return array<int, self>
     */
    public static function blockingStatuses(): array
    {
        return [
            self::PENDING,
            self::FAILED,
            self::MANUAL_ACTION_REQUIRED,
        ];
    }
}
