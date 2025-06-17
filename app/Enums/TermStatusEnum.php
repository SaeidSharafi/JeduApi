<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\AdvanceEnum;

enum TermStatusEnum: string
{
    use AdvanceEnum;

    case ACTIVE   = 'active';
    case INACTIVE = 'inactive';
    case PLANNING = 'planning';
}
