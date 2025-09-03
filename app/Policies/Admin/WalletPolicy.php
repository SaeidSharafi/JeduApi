<?php

declare(strict_types=1);

namespace App\Policies\Admin;

use App\Models\Staff;
use App\Models\Wallet;
use App\Enums\PermissionEnum;

class WalletPolicy
{
    public function viewAny(Staff $user): bool
    {
        return $user->can(PermissionEnum::WALLET_VIEW_ANY->value);
    }
    public function create(Staff $user): bool
    {
        return $user->can(PermissionEnum::WALLET_CREATE->value);
    }

    public function view(Staff $user, Wallet $wallet): bool
    {
        return $user->can(PermissionEnum::WALLET_VIEW->value);
    }

    public function update(Staff $user, Wallet $wallet): bool
    {
        return $user->can(PermissionEnum::WALLET_UPDATE->value);
    }

    public function delete(Staff $user, Wallet $wallet): bool
    {
        return $user->can(PermissionEnum::WALLET_DELETE->value);
    }
}
