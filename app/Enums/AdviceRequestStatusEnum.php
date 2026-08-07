<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\AdvanceEnum;

enum AdviceRequestStatusEnum: string
{
    /** @use AdvanceEnum<value-of<self>> */
    use AdvanceEnum;
    case PENDING     = 'pending';
    case CONTACTED   = 'contacted';
    case RESOLVED    = 'resolved';
    case NO_RESPONSE = 'no_response';
}
