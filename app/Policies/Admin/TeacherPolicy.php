<?php

declare(strict_types=1);

namespace App\Policies\Admin;

use App\Enums\PermissionEnum;
use App\Models\Staff;
use App\Models\Teacher;

final class TeacherPolicy
{
    /**
     * Determine whether the Staff can view any models.
     */
    public function viewAny(Staff $user): bool
    {
        return $user->can(PermissionEnum::TEACHER_VIEW_ANY->value);
    }

    /**
     * Determine whether the Staff can view the model.
     */
    public function view(Staff $user, Teacher $teacher): bool
    {
        return $user->can(PermissionEnum::TEACHER_VIEW->value);
    }

    /**
     * Determine whether the Staff can create models.
     */
    public function create(Staff $user): bool
    {
        return $user->can(PermissionEnum::TEACHER_CREATE->value);
    }

    /**
     * Determine whether the Staff can update the model.
     */
    public function update(Staff $user, Teacher $teacher): bool
    {
        return $user->can(PermissionEnum::TEACHER_UPDATE->value);
    }

    /**
     * Determine whether the Staff can delete the model.
     */
    public function delete(Staff $user, Teacher $teacher): bool
    {
        return $user->can(PermissionEnum::TEACHER_DELETE->value);
    }
}
