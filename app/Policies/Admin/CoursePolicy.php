<?php

namespace App\Policies\Admin;

use App\Enums\PermissionEnum;
use App\Models\Course;
use App\Models\Admin;

class CoursePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(Admin $user): bool
    {
        return $user->can(PermissionEnum::COURSE_VIEW_ANY->value);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(Admin $user, Course $course): bool
    {
        return $user->can(PermissionEnum::COURSE_VIEW->value);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(Admin $user): bool
    {
        return $user->can(PermissionEnum::COURSE_CREATE->value);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(Admin $user, Course $course): bool
    {
        return $user->can(PermissionEnum::COURSE_UPDATE->value);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(Admin $user, Course $course): bool
    {
        return $user->can(PermissionEnum::COURSE_DELETE->value);
    }

}
