<?php

declare(strict_types=1);

namespace App\Events\Wallet;

use App\Models\User;
use App\Models\WalletCampaign;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WalletCampaignAllocationTriggeredEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public WalletTransaction $transaction,
        public WalletCampaign $campaign,
        public User $user,
        public string $triggerType
    ) {}
}
