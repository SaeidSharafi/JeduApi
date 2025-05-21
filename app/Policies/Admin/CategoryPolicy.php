<?php

namespace App\Policies\Admin;

use App\Enums\PermissionEnum;
use App\Models\Category;
use App\Models\Admin;

class CategoryPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(Admin $user): bool
    {
        return $user->can(PermissionEnum::CATEGORY_VIEW_ANY->value);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(Admin $user, Category $category): bool
    {
        return $user->can(PermissionEnum::CATEGORY_VIEW->value);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(Admin $user): bool
    {
        return $user->can(PermissionEnum::CATEGORY_CREATE->value);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(Admin $user, Category $category): bool
    {
        return $user->can(PermissionEnum::CATEGORY_UPDATE->value);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(Admin $user, Category $category): bool
    {
        return $user->can(PermissionEnum::CATEGORY_DELETE->value);
    }

}
