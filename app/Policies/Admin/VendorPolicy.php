<?php

namespace App\Policies\Admin;

use App\Enums\PermissionEnum;
use App\Models\Staff;
use App\Models\Vendor;

class VendorPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(Staff $user): bool
    {
        return $user->can(PermissionEnum::VENDOR_VIEW_ANY);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(Staff $user, Vendor $vendor): bool
    {
        return $user->can(PermissionEnum::VENDOR_VIEW);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(Staff $user): bool
    {
        return $user->can(PermissionEnum::VENDOR_CREATE);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(Staff $user, Vendor $vendor): bool
    {
        return $user->can(PermissionEnum::VENDOR_UPDATE);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(Staff $user, Vendor $vendor): bool
    {
        return $user->can(PermissionEnum::VENDOR_DELETE);
    }

}
