<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\AdvanceEnum;

enum EducationStatusEnum: string
{
    use AdvanceEnum;
    case STUDENT    = 'student';
    case UNIVERSITY = 'university';
    case GRADUATED  = 'graduated';
    case EMPLOYED   = 'employed';
}
