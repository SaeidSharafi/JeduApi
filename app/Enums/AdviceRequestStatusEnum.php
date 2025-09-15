<?php

namespace App\Enums;

use App\Traits\AdvanceEnum;

enum AdviceRequestStatusEnum: string
{
    use AdvanceEnum;
    case PENDING = 'pending';
    case CONTACTED = 'contacted';
    case RESOLVED = 'resolved';
    case NO_RESPONSE = 'no_response';
}
