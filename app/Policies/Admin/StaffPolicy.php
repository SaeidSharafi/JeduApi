<?php

declare(strict_types=1);

namespace App\Policies\Admin;

use App\Enums\PermissionEnum;
use App\Models\Staff;
use Illuminate\Auth\Access\HandlesAuthorization;

final class StaffPolicy
{
    use HandlesAuthorization;

    public function viewAny(Staff $user): bool
    {
        return $user->can(PermissionEnum::STAFF_VIEW_ANY);
    }

    public function view(Staff $user, Staff $model): bool
    {
        return $user->can(PermissionEnum::STAFF_VIEW) || $user->id === $model->id;
    }

    public function create(Staff $user): bool
    {
        return $user->can(PermissionEnum::STAFF_CREATE);
    }

    public function update(Staff $user, Staff $model): bool
    {
        if ($user->id === $model->id) {
            return true;
        }
        if ($user->is_admin && $model->is_admin) {
            return false;
        }
        if (! $user->is_admin && $model->is_admin) {
            return false;
        }

        return $user->can(PermissionEnum::STAFF_UPDATE);
    }

    public function delete(Staff $user, Staff $model): bool
    {
        if ($user->id === $model->id) {
            return false;
        }
        if ($user->is_admin && $model->is_admin) {
            return false;
        }
        if (! $user->is_admin && $model->is_admin) {
            return false;
        }

        return $user->can(PermissionEnum::STAFF_DELETE);
    }

    public function ban(Staff $user, Staff $model): bool
    {
        return $user->can(PermissionEnum::STAFF_BAN);
    }
}
