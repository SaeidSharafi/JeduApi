<?php

declare(strict_types=1);

namespace App\Policies\Admin;

use App\Enums\PermissionEnum;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class UserPolicy
{
    use HandlesAuthorization;

    public function viewAny(Staff $staff): bool
    {
        return $staff->can(PermissionEnum::USER_VIEW_ANY->value);
    }

    public function view(Staff $staff, User $user): bool
    {
        return $staff->can(PermissionEnum::USER_VIEW->value);
    }

    public function create(Staff $staff): bool
    {
        return $staff->can(PermissionEnum::USER_CREATE->value);
    }

    public function update(Staff $staff, User $user): bool
    {
        return $staff->can(PermissionEnum::USER_UPDATE->value);
    }

    public function delete(Staff $staff, User $user): bool
    {
        return $staff->can(PermissionEnum::USER_DELETE->value);
    }
}
