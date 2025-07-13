<?php

namespace App\Enums;

use App\Traits\AdvanceEnum;

enum ProductableMediaTypeEnum: string
{
    use AdvanceEnum;

    case GALLERY = 'gallery';
    case VIDEO = 'video';
    case COVER = 'cover';
    case CERTIFICATE = 'certificate';

}
