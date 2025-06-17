<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\AdvanceEnum;

enum GenderEnum: string
{
    use AdvanceEnum;
    case MALE   = 'male';
    case FEMALE = 'female';

}
