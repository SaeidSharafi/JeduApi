<?php

namespace App\Policies\Admin;

use App\Enums\PermissionEnum;
use App\Models\Staff;
use Illuminate\Auth\Access\HandlesAuthorization;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    use HandlesAuthorization;

    public function viewAny(Staff $user): bool
    {
        return $user->can(PermissionEnum::ROLE_VIEW_ANY->value);
    }

    public function view(Staff $user, Role $role): bool
    {
        return $user->can(PermissionEnum::ROLE_VIEW->value);
    }

    public function create(Staff $user): bool
    {
        return $user->can(PermissionEnum::ROLE_CREATE->value);
    }

    public function update(Staff $user, Role $role): bool
    {
        if ($user->hasRole($role->id)){
            return false;
        }
        return $user->can(PermissionEnum::ROLE_UPDATE->value);
    }

    public function delete(Staff $user, Role $role): bool
    {
        if ($user->hasRole($role->id)){
            return false;
        }
        return $user->can(PermissionEnum::ROLE_DELETE->value);
    }
}
