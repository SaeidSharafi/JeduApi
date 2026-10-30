<?php

declare(strict_types=1);

namespace App\Enums\WalletCampaign;

use App\Traits\AdvanceEnum;

/**
 * Canonical trigger-event vocabulary shared by the domain-event subscriber,
 * the threshold evaluator, the birthday sweep, and the allocation ledger.
 */
enum CampaignTriggerEventEnum: string
{
    /** @use AdvanceEnum<value-of<self>> */
    use AdvanceEnum;

    case PROFILE_COMPLETED = 'profile_completed';
    case PAYMENT_COMPLETED = 'payment_completed';
    case BIRTHDAY_SWEEP    = 'birthday_sweep';
}
