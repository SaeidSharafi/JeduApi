<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\AdvanceEnum;

enum MediaTagEnum: string
{
    /** @use AdvanceEnum<value-of<self>> */
    use AdvanceEnum;

    case GALLERY     = 'gallery';
    case VIDEO       = 'video';
    case COVER       = 'cover';
    case CERTIFICATE = 'certificate';
    case MAIN        = 'main';
}
