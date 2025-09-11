<?php

declare(strict_types=1);

namespace App\Enums\Order;

use App\Traits\AdvanceEnum;

enum OrderStatusEnum: string
{
    use AdvanceEnum;

    case PENDING            = 'pending';
    case PROCESSING         = 'processing';
    case COMPLETED          = 'completed';
    case CANCELLED          = 'cancelled';
    case FAILED             = 'failed';
    case REFUNDED           = 'refunded';
    case PARTIALLY_REFUNDED = 'partially_refunded';
}
