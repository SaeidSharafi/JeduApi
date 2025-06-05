<?php

namespace App\Enums;

use App\Traits\AdvanceEnum;

enum TermStatusEnum: string
{
    use AdvanceEnum;

    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case PLANNING = 'planning';
}
