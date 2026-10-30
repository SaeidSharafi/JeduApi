<?php

declare(strict_types=1);

namespace App\Enums\WalletCampaign;

use App\Traits\AdvanceEnum;

/**
 * Who fires an allocation: a customer-facing domain event or an admin grant.
 */
enum CampaignTriggerTypeEnum: string
{
    /** @use AdvanceEnum<value-of<self>> */
    use AdvanceEnum;

    case MANUAL = 'manual';
    case EVENT  = 'event';
}
