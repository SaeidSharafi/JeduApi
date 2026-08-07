<?php

declare(strict_types=1);

namespace App\Enums\User;

use App\Traits\AdvanceEnum;

enum EducationStatusEnum: string
{
    /** @use AdvanceEnum<value-of<self>> */
    use AdvanceEnum;
    case STUDENT    = 'student';
    case UNIVERSITY = 'university';
    case GRADUATED  = 'graduated';
    case EMPLOYED   = 'employed';
    case JOB_SEEKER = 'job_seeker';
}
