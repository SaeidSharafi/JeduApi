<?php

declare(strict_types=1);

namespace App\Actions\Admin\WalletCampaign;

use App\Data\Admin\WalletCampaign\WalletCampaignCreateData;
use App\Models\Staff;
use App\Models\WalletCampaign;
use Illuminate\Support\Facades\DB;

final readonly class CreateWalletCampaignAction
{
    /**
     * Create a new wallet campaign
     */
    public function execute(WalletCampaignCreateData $data, Staff $staff): WalletCampaign
    {
        return DB::transaction(function () use ($data, $staff) {
            $campaign = WalletCampaign::create([
                'name'                 => $data->name,
                'description'          => $data->description,
                'type'                 => $data->type,
                'is_active'            => $data->is_active,
                'amount'               => $data->amount,
                'usage_limit_total'    => $data->usage_limit_total,
                'usage_limit_per_user' => $data->usage_limit_per_user,
                'total_usage_count'    => 0,
                'starts_at'            => $data->starts_at ? now()->parse($data->starts_at) : null,
                'ends_at'              => $data->ends_at ? now()->parse($data->ends_at) : null,
                'metadata'             => $data->metadata,
                'created_by'           => $staff->id,
            ]);

            return $campaign;
        });
    }
}
