<?php

declare(strict_types=1);

namespace App\Enums\Order;

use App\Traits\AdvanceEnum;

enum OrderCancellationReasonEnum: string
{
    /** @use AdvanceEnum<value-of<self>> */
    use AdvanceEnum;

    /** Customer cancelled their own pending order. */
    case CUSTOMER = 'customer';

    /** Staff cancelled or closed the order through the admin API. */
    case ADMIN = 'admin';

    /** The inactivity sweeper cancelled the order (orders:cancel-abandoned). */
    case ABANDONED = 'abandoned';

    /** A system transition derived the cancelled status from the order items. */
    case SYSTEM = 'system';
}
