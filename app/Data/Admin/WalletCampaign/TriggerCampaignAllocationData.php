<?php

declare(strict_types=1);

namespace App\Data\Admin\WalletCampaign;

use App\Enums\WalletCampaign\CampaignTriggerEventEnum;
use App\Enums\WalletCampaign\CampaignTriggerTypeEnum;
use Spatie\LaravelData\Data;

/**
 * Internal input for the campaign allocation funnel. Event/sweep callers pass
 * a business key implicitly; admin grants pass a request-scoped key.
 */
final class TriggerCampaignAllocationData extends Data
{
    public function __construct(
        public CampaignTriggerTypeEnum $trigger_type,
        public ?CampaignTriggerEventEnum $trigger_event,
        public ?string $trigger_occurrence = null,
        public ?string $reason = null,
        public ?array $metadata = null,
        public ?string $idempotency_key = null,
    ) {}
}
