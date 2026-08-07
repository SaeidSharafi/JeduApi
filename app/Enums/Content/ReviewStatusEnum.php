<?php

declare(strict_types=1);

namespace App\Enums\Content;

use App\Traits\AdvanceEnum;

enum ReviewStatusEnum: string
{
    /** @use AdvanceEnum<value-of<self>> */
    use AdvanceEnum;

    case PENDING  = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
}
