<?php

declare(strict_types=1);

namespace App\Policies\Admin;

use App\Enums\PermissionEnum;
use App\Models\AdminActionLog;
use App\Models\Staff;
use Illuminate\Auth\Access\HandlesAuthorization;

final class AdminActionLogPolicy
{
    use HandlesAuthorization;

    public function viewAny(Staff $user): bool
    {
        return $user->can(PermissionEnum::AUDIT_ADMIN_ACTIONS_VIEW->value);
    }

    public function view(Staff $user, AdminActionLog $adminActionLog): bool
    {
        return $user->can(PermissionEnum::AUDIT_ADMIN_ACTIONS_VIEW->value);
    }
}
