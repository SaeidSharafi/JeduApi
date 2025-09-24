<?php

declare(strict_types=1);

namespace App\Policies\Admin;

use App\Enums\PermissionEnum;
use App\Models\Partner;
use App\Models\Staff;
use Illuminate\Auth\Access\HandlesAuthorization;

final class PartnerPolicy
{
    use HandlesAuthorization;

    public function viewAny(Staff $user): bool
    {
        return $user->can(PermissionEnum::PARTNER_VIEW_ANY);
    }

    public function view(Staff $user, Partner $partner): bool
    {
        return $user->can(PermissionEnum::PARTNER_VIEW);
    }

    public function create(Staff $user): bool
    {
        return $user->can(PermissionEnum::PARTNER_CREATE);
    }

    public function update(Staff $user, Partner $partner): bool
    {
        return $user->can(PermissionEnum::PARTNER_UPDATE);
    }

    public function delete(Staff $user, Partner $partner): bool
    {
        return $user->can(PermissionEnum::PARTNER_DELETE);
    }
}
