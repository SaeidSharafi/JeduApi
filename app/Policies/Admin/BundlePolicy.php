<?php

declare(strict_types=1);

namespace App\Policies\Admin;

use App\Enums\PermissionEnum;
use App\Models\Bundle;
use App\Models\Staff;

final class BundlePolicy
{
    public function viewAny(Staff $user): bool
    {
        return $user->can(PermissionEnum::PRODUCT_VIEW_ANY->value);
    }

    public function view(Staff $user, Bundle $bundle): bool
    {
        return $user->can(PermissionEnum::PRODUCT_VIEW->value);
    }

    public function create(Staff $user): bool
    {
        return $user->can(PermissionEnum::PRODUCT_CREATE->value);
    }

    public function update(Staff $user, Bundle $bundle): bool
    {
        return $user->can(PermissionEnum::PRODUCT_UPDATE->value);
    }

    public function delete(Staff $user, Bundle $bundle): bool
    {
        return $user->can(PermissionEnum::PRODUCT_DELETE->value);
    }
}
