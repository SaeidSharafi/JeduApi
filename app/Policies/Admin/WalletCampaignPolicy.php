<?php

declare(strict_types=1);

namespace App\Policies\Admin;

use App\Enums\PermissionEnum;
use App\Models\Staff;
use App\Models\WalletCampaign;

final class WalletCampaignPolicy
{
    /**
     * Determine whether the user can view any wallet campaigns.
     */
    public function viewAny(Staff $user): bool
    {
        return $user->can(PermissionEnum::WALLET_CAMPAIGN_VIEW_ANY->value);
    }

    /**
     * Determine whether the user can view the wallet campaign.
     */
    public function view(Staff $user, WalletCampaign $walletCampaign): bool
    {
        return $user->can(PermissionEnum::WALLET_CAMPAIGN_VIEW->value);
    }

    /**
     * Determine whether the user can create wallet campaigns.
     */
    public function create(Staff $user): bool
    {
        return $user->can(PermissionEnum::WALLET_CAMPAIGN_CREATE->value);
    }

    /**
     * Determine whether the user can update the wallet campaign.
     */
    public function update(Staff $user, WalletCampaign $walletCampaign): bool
    {
        return $user->can(PermissionEnum::WALLET_CAMPAIGN_UPDATE->value);
    }

    /**
     * Determine whether the user can delete the wallet campaign.
     */
    public function delete(Staff $user, WalletCampaign $walletCampaign): bool
    {
        return $user->can(PermissionEnum::WALLET_CAMPAIGN_DELETE->value);
    }

    /**
     * Determine whether the user can allocate gift credit from the campaign.
     */
    public function allocate(Staff $user, WalletCampaign $walletCampaign): bool
    {
        return $user->can(PermissionEnum::WALLET_CAMPAIGN_ALLOCATE->value);
    }
}
