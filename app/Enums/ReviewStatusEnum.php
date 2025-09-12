<?php

namespace App\Enums;

use App\Traits\AdvanceEnum;

enum ReviewStatusEnum: string
{
    use AdvanceEnum;

    case PENDING  = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
}
