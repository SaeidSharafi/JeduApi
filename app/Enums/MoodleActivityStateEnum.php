<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\AdvanceEnum;

enum MoodleActivityStateEnum: int
{
    use AdvanceEnum;
    case INCOMPLETE    = 0;
    case COMPLETE      = 1;
    case COMPLETE_PASS = 2;
    case COMPLETE_FAIL = 3;

}
