<?php

namespace App\Policies\Admin;

use App\Enums\PermissionEnum;
use App\Models\Product;
use App\Models\Staff;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProductPolicy
{
    use HandlesAuthorization;

    public function viewAny(Staff $user): bool
    {
        return $user->can(PermissionEnum::PRODUCT_VIEW_ANY->value);
    }

    public function view(Staff $user, Product $product): bool
    {
        return $user->can(PermissionEnum::PRODUCT_VIEW->value);
    }

    public function create(Staff $user): bool
    {
        return $user->can(PermissionEnum::PRODUCT_CREATE->value);
    }

    public function update(Staff $user, Product $product): bool
    {
        return $user->can(PermissionEnum::PRODUCT_UPDATE->value);
    }

    public function delete(Staff $user, Product $product): bool
    {
        return $user->can(PermissionEnum::PRODUCT_DELETE->value);
    }
}
