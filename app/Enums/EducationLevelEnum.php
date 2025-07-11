<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\AdvanceEnum;

enum EducationLevelEnum: string
{
    use AdvanceEnum;
    case STUDENT       = 'student';
    case UNDER_DIPLOMA = 'under_diploma';
    case DIPLOMA       = 'diploma';
    case ASSOCIATE     = 'associate';
    case BACHELOR      = 'bachelor';
    case MASTER        = 'master';
    case DOCTORATE     = 'doctorate';
    case POSTDOCTORAL  = 'postdoctoral';

}
