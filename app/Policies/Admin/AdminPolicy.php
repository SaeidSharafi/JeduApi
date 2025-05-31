<?php

namespace App\Policies\Admin;

use App\Enums\PermissionEnum;
use App\Models\Admin;
use Illuminate\Auth\Access\HandlesAuthorization;

class AdminPolicy
{
    use HandlesAuthorization;

    public function viewAny(Admin $user): bool
    {
        return $user->can(PermissionEnum::ADMIN_VIEW_ANY);
    }

    public function view(Admin $user, Admin $model): bool
    {
        return $user->can(PermissionEnum::ADMIN_VIEW) || $user->id === $model->id;
    }

    public function create(Admin $user): bool
    {
        return $user->can(PermissionEnum::ADMIN_CREATE);
    }

    public function update(Admin $user, Admin $model): bool
    {
        if ($user->id === $model->id){
            return true;
        }
        if ($user->is_admin && $model->is_admin) {
            return false;
        }
        if (!$user->is_admin && $model->is_admin){
            return false;
        }

        return $user->can(PermissionEnum::ADMIN_UPDATE);
    }

    public function delete(Admin $user, Admin $model): bool
    {
        if ($user->id === $model->id){
            return true;
        }
        if ($user->is_admin && $model->is_admin) {
            return false;
        }
        if (!$user->is_admin && $model->is_admin){
            return false;
        }
        return $user->can(PermissionEnum::ADMIN_DELETE) && $user->id !== $model->id;
    }

}
