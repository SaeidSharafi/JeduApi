<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\AdvanceEnum;

enum CourseDifficultyLevelEnum: string
{
    use AdvanceEnum;

    case BEGINNER     = 'beginner';
    case INTERMEDIATE = 'intermediate';
    case ADVANCED     = 'advanced';
    case EXPERT       = 'expert';

}
