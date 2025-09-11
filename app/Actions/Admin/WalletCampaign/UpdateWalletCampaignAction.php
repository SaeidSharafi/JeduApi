<?php

declare(strict_types=1);

namespace App\Actions\Admin\WalletCampaign;

use App\Data\Admin\WalletCampaign\WalletCampaignCreateData;
use App\Models\WalletCampaign;
use Illuminate\Support\Facades\DB;

final readonly class UpdateWalletCampaignAction
{
    /**
     * Update an existing wallet campaign
     */
    public function execute(WalletCampaign $campaign, WalletCampaignCreateData $data): WalletCampaign
    {
        return DB::transaction(function () use ($campaign, $data) {
            $campaign->update([
                'name'                 => $data->name,
                'description'          => $data->description,
                'type'                 => $data->type,
                'is_active'            => $data->is_active,
                'amount'               => $data->amount,
                'usage_limit_total'    => $data->usage_limit_total,
                'usage_limit_per_user' => $data->usage_limit_per_user,
                'starts_at'            => $data->starts_at ? now()->parse($data->starts_at) : null,
                'ends_at'              => $data->ends_at ? now()->parse($data->ends_at) : null,
                'metadata'             => $data->metadata,
            ]);

            return $campaign;
        });
    }
}
