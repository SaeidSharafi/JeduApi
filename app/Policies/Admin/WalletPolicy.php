<?php

declare(strict_types=1);

namespace App\Policies\Admin;

use App\Enums\PermissionEnum;
use App\Models\Staff;
use App\Models\Wallet;

final class WalletPolicy
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

    public function deposit(Staff $user, Wallet $wallet): bool
    {
        return $user->can(PermissionEnum::WALLET_DEPOSIT->value);
    }

    public function withdrawal(Staff $user, Wallet $wallet): bool
    {
        return $user->can(PermissionEnum::WALLET_WITHDRAWAL->value);
    }

    public function adjustment(Staff $user, Wallet $wallet): bool
    {
        return $user->can(PermissionEnum::WALLET_ADJUSTMENT->value);
    }
}
