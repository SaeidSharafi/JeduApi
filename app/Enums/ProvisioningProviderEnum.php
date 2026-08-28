<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\AdvanceEnum;

enum ProvisioningProviderEnum: string
{
    /** @use AdvanceEnum<value-of<self>> */
    use AdvanceEnum;

    case IMS         = 'ims';
    case MOODLE      = 'moodle';
    case SPOTPLAYER  = 'spotplayer';
    case BBB         = 'bbb';
    case SKYROOM     = 'skyroom';
    case MOODLE_QUIZ = 'moodle_quiz';
}
