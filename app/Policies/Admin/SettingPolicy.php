<?php

declare(strict_types=1);

namespace App\Policies\Admin;

use App\Enums\PermissionEnum;
use App\Models\Staff;
use Illuminate\Auth\Access\HandlesAuthorization;

final class SettingPolicy
{
    use HandlesAuthorization;

    public function viewAny(Staff $user): bool
    {
        return $user->can(PermissionEnum::SETTING_VIEW_ANY);
    }

    public function update(Staff $user): bool
    {
        return $user->can(PermissionEnum::SETTING_UPDATE);
    }
}
