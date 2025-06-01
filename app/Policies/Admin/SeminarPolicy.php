<?php

declare(strict_types=1);

namespace App\Policies\Admin;

use App\Enums\PermissionEnum;
use App\Models\Staff;
use App\Models\Seminar;

final class SeminarPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(Staff $user): bool
    {
        return $user->can(PermissionEnum::SEMINAR_VIEW_ANY->value);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(Staff $user, Seminar $seminar): bool
    {
        return $user->can(PermissionEnum::SEMINAR_VIEW->value);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(Staff $user): bool
    {
        return $user->can(PermissionEnum::SEMINAR_CREATE->value);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(Staff $user, Seminar $seminar): bool
    {
        return $user->can(PermissionEnum::SEMINAR_UPDATE->value);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(Staff $user, Seminar $seminar): bool
    {
        return $user->can(PermissionEnum::SEMINAR_DELETE->value);
    }
}
