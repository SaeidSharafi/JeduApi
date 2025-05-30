<?php

declare(strict_types=1);

namespace App\Policies\Admin;

use App\Enums\PermissionEnum;
use App\Models\Admin;
use App\Models\Seminar;

final class SeminarPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(Admin $user): bool
    {
        return $user->can(PermissionEnum::SEMINAR_VIEW_ANY->value);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(Admin $user, Seminar $seminar): bool
    {
        return $user->can(PermissionEnum::SEMINAR_VIEW->value);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(Admin $user): bool
    {
        return $user->can(PermissionEnum::SEMINAR_CREATE->value);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(Admin $user, Seminar $seminar): bool
    {
        return $user->can(PermissionEnum::SEMINAR_UPDATE->value);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(Admin $user, Seminar $seminar): bool
    {
        return $user->can(PermissionEnum::SEMINAR_DELETE->value);
    }
}
