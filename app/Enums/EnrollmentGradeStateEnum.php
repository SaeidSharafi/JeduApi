<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\AdvanceEnum;

enum EnrollmentGradeStateEnum: string
{
    /** @use AdvanceEnum<value-of<self>> */
    use AdvanceEnum;

    case NOT_SUPPORTED = 'not_supported';
    case LOCKED        = 'locked';
    case PENDING       = 'pending';
    case AVAILABLE     = 'available';
    case UNAVAILABLE   = 'unavailable';
}
