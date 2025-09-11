<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\AdvanceEnum;

enum ProductableMediaTypeEnum: string
{
    use AdvanceEnum;

    case GALLERY     = 'gallery';
    case VIDEO       = 'video';
    case COVER       = 'cover';
    case CERTIFICATE = 'certificate';

}
