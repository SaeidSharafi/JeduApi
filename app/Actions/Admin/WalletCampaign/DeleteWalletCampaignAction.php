<?php

namespace App\Actions\Admin\WalletCampaign;

use App\Exceptions\ModelHasRelationshipDataException;
use App\Models\WalletCampaign;
use App\Models\WalletTransaction;

class DeleteWalletCampaignAction
{

    public function handle(WalletCampaign $campaign): void
    {
        // Check if campaign has transactions
        if ($campaign->transactions()->exists()) {
           throw new ModelHasRelationshipDataException(WalletTransaction::class);
        }
        $campaign->delete();
    }

}
