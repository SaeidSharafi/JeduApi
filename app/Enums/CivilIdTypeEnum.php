<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\AdvanceEnum;

enum CivilIdTypeEnum: string
{
    use AdvanceEnum;
    /**
     * For Iranian citizens (کد ملی).
     */
    case NATIONAL_CODE = 'national_code';

    /**
     * For immigrants/foreigners with a Faragir code (کد فراگیر).
     */
    case IMMIGRANT_CODE = 'immigrant_code';

    /**
     * For any user identified by a passport, a good future-proof option.
     */
    case PASSPORT = 'passport';

}
