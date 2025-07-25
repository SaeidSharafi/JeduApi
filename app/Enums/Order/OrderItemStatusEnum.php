<?php

declare(strict_types=1);

namespace App\Enums\Order;

use App\Traits\AdvanceEnum;

enum OrderItemStatusEnum: string
{
    use AdvanceEnum;

    case PENDING    = 'pending';
    case COMPLETED    = 'completed';
    case CANCELLED = 'cancelled';
    case REFUNDED  = 'refunded';
}
