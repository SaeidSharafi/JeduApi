<?php

declare(strict_types=1);

namespace App\Actions\Admin\WalletCampaign;

use App\Exceptions\ModelHasRelationshipDataException;
use App\Models\WalletCampaign;
use App\Models\WalletTransaction;

final class DeleteWalletCampaignAction
{
    public function handle(WalletCampaign $campaign): void
    {
        // Check if campaign has transactions
        if ($campaign->transactions()->exists()) {
            throw new ModelHasRelationshipDataException(
                WalletTransaction::class,
                __('messages.campaign_has_transactions_cannot_delete')
            );
        }
        $campaign->delete();
    }
}
