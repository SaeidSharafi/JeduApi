<?php

namespace App\Enums;

use App\Traits\AdvanceEnum;

enum CourseDifficultyLevelEnum: string
{
    use AdvanceEnum;

    case BEGINNER = 'beginner';
    case INTERMEDIATE = 'intermediate';
    case ADVANCED = 'advanced';
    case EXPERT = 'expert';

}
