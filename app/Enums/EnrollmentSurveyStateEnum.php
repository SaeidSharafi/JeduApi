<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\AdvanceEnum;

enum EnrollmentSurveyStateEnum: string
{
    /** @use AdvanceEnum<value-of<self>> */
    use AdvanceEnum;

    case NOT_REQUIRED    = 'not_required';
    case AWAITING_SURVEY = 'awaiting_survey';
    case PENDING         = 'pending';
    case COMPLETED       = 'completed';

    public function locksResults(): bool
    {
        return $this === self::AWAITING_SURVEY || $this === self::PENDING;
    }
}
