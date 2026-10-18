<?php

declare(strict_types=1);

namespace App\Enums\Order;

use App\Traits\AdvanceEnum;

enum OrderStatusEnum: string
{
    /** @use AdvanceEnum<value-of<self>> */
    use AdvanceEnum;

    case PENDING            = 'pending';
    case PROCESSING         = 'processing';
    case COMPLETED          = 'completed';
    case CANCELLED          = 'cancelled';
    case FAILED             = 'failed';
    case REFUNDED           = 'refunded';
    case PARTIALLY_REFUNDED = 'partially_refunded';

    /**
     * A closed order can no longer expect a new payment, so its open payment
     * attempts are failed when it reaches this status. PROCESSING stays open
     * because a remainder payment may still be expected.
     */
    public function closesPaymentAttempts(): bool
    {
        return match ($this) {
            self::PENDING, self::PROCESSING => false,
            default                         => true,
        };
    }
}
