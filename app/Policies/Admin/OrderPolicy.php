<?php

declare(strict_types=1);

namespace App\Policies\Admin;

use App\Enums\PermissionEnum;
use App\Models\Order;
use App\Models\Staff;
use Illuminate\Auth\Access\HandlesAuthorization;

final class OrderPolicy
{
    use HandlesAuthorization;

    public function viewAny(Staff $user): bool
    {
        return $user->can(PermissionEnum::ORDER_VIEW_ANY->value);
    }

    public function view(Staff $user, Order $order): bool
    {
        return $user->can(PermissionEnum::ORDER_VIEW->value);
    }

    public function create(Staff $user): bool
    {
        return $user->can(PermissionEnum::ORDER_CREATE->value);
    }

    public function update(Staff $user, Order $order): bool
    {
        return $user->can(PermissionEnum::ORDER_UPDATE->value);
    }

    public function delete(Staff $user, Order $order): bool
    {
        return $user->can(PermissionEnum::ORDER_DELETE->value);
    }
}
