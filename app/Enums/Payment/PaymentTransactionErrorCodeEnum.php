<?php

declare(strict_types=1);

namespace App\Enums\Payment;

use App\Traits\AdvanceEnum;

/**
 * Error codes the application itself writes to
 * payment_transactions.error_code.
 *
 * Gateway codes (Mellat ResCode, Digipay codes, …) are an external vocabulary
 * and stay raw strings. Dashboards use this enum to separate system/business
 * outcomes (order closed, attempt timed out) from real gateway failures.
 */
enum PaymentTransactionErrorCodeEnum: string
{
    /** @use AdvanceEnum<value-of<self>> */
    use AdvanceEnum;

    /** The order reached a terminal state before the attempt resolved. */
    case ORDER_CLOSED = 'ORDER_CLOSED';

    /** No gateway result arrived before the attempt deadline. */
    case STUCK_TIMEOUT = 'STUCK_TIMEOUT';
}
