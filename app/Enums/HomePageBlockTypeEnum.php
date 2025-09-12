<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\AdvanceEnum;

enum HomePageBlockTypeEnum: string
{
    use AdvanceEnum;

    case MAIN_CATEGORIES = 'MAIN_CATEGORIES';
    case BANNER = 'BANNER';
    case CURATED_LIST = 'CURATED_LIST';
    case WEBINAR_BANNER = 'WEBINAR_BANNER';
}
