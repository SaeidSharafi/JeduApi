<?php

declare(strict_types=1);

namespace App\Policies\Admin;

use App\Enums\PermissionEnum;
use App\Models\Staff;
use Illuminate\Auth\Access\HandlesAuthorization;

final class ImportRunPolicy
{
    use HandlesAuthorization;

    public function preview(Staff $staff): bool
    {
        return $staff->can(PermissionEnum::IMPORT_PREVIEW->value);
    }

    public function downloadTemplate(Staff $staff): bool
    {
        return $staff->can(PermissionEnum::IMPORT_TEMPLATE->value);
    }

    public function approve(Staff $staff): bool
    {
        return $staff->can(PermissionEnum::IMPORT_APPROVE->value);
    }
}
