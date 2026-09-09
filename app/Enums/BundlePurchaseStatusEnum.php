<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\AdvanceEnum;

/**
 * Aggregate, derived status of one immutable Bundle Purchase.
 *
 * Value vocabulary follows the Bundle program spec (parent #7): the purchase
 * is grouped from payment state and every component's OrderItem, Enrollment,
 * and provisioning state. `cancelled` covers a purchase whose order was
 * cancelled before any payment completed.
 */
enum BundlePurchaseStatusEnum: string
{
    /** @use AdvanceEnum<value-of<self>> */
    use AdvanceEnum;

    case PENDING_PAYMENT    = 'pending_payment';
    case PROVISIONING       = 'provisioning';
    case ACTIVE             = 'active';
    case PARTIALLY_FAILED   = 'partially_failed';
    case FAILED             = 'failed';
    case REVOCATION_PENDING = 'revocation_pending';
    case REFUNDED           = 'refunded';
    case CANCELLED          = 'cancelled';
}
