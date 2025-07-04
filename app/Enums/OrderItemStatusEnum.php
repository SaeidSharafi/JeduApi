<?php

namespace App\Enums;

use App\Traits\AdvanceEnum;

enum OrderItemStatusEnum: string
{
    use AdvanceEnum;

    case ACTIVE = 'active';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';
}
