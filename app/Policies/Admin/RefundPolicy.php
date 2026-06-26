<?php

declare(strict_types=1);

namespace App\Policies\Admin;

use App\Enums\PermissionEnum;
use App\Models\Refund;
use App\Models\Staff;
use Illuminate\Auth\Access\HandlesAuthorization;

final class RefundPolicy
{
    use HandlesAuthorization;

    public function viewAny(Staff $user): bool
    {
        return $user->can(PermissionEnum::REFUND_VIEW_ANY);
    }

    public function view(Staff $user, Refund $refund): bool
    {
        return $user->can(PermissionEnum::REFUND_VIEW);
    }

    public function create(Staff $user): bool
    {
        return $user->can(PermissionEnum::REFUND_CREATE);
    }

    public function update(Staff $user, Refund $refund): bool
    {
        return $user->can(PermissionEnum::REFUND_UPDATE);
    }

    public function delete(Staff $user, Refund $refund): bool
    {
        return $user->can(PermissionEnum::REFUND_DELETE);
    }

    public function updateStatus(Staff $user, Refund $refund): bool
    {
        return $user->can(PermissionEnum::REFUND_UPDATE_STATUS);
    }

    public function skipGateway(Staff $user): bool
    {
        return $user->can(PermissionEnum::REFUND_SKIP_GATEWAY->value);
    }
}
