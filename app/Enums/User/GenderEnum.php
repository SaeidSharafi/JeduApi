<?php

declare(strict_types=1);

namespace App\Enums\User;

use App\Traits\AdvanceEnum;

enum GenderEnum: string
{
    /** @use AdvanceEnum<value-of<self>> */
    use AdvanceEnum;
    case MALE   = 'male';
    case FEMALE = 'female';

}
