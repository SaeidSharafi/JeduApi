<?php

declare(strict_types=1);

namespace App\Policies\Admin;

use App\Enums\PermissionEnum;
use App\Models\Enrollment;
use App\Models\Staff;
use Illuminate\Auth\Access\HandlesAuthorization;

final class EnrollmentPolicy
{
    use HandlesAuthorization;

    public function viewAny(Staff $user): bool
    {
        return $user->can(PermissionEnum::ENROLLMENT_VIEW_ANY->value);
    }

    public function view(Staff $user, Enrollment $enrollment): bool
    {
        return $user->can(PermissionEnum::ENROLLMENT_VIEW->value);
    }

    public function update(Staff $user, Enrollment $enrollment): bool
    {
        return $user->can(PermissionEnum::ENROLLMENT_UPDATE->value);
    }

    public function changeStatus(Staff $user, Enrollment $enrollment): bool
    {
        return $user->can(PermissionEnum::ENROLLMENT_UPDATE->value);
    }

    public function retryProvisioning(Staff $user, Enrollment $enrollment): bool
    {
        return $user->can(PermissionEnum::ENROLLMENT_RETRY_PROVISION->value);
    }

    public function resolveProvisioning(Staff $user, Enrollment $enrollment): bool
    {
        return $user->can(PermissionEnum::ENROLLMENT_RETRY_PROVISION->value);
    }

    public function waiveProvisioning(Staff $user, Enrollment $enrollment): bool
    {
        return $user->can(PermissionEnum::ENROLLMENT_WAIVE_PROVISION->value);
    }

    public function delete(Staff $user, Enrollment $enrollment): bool
    {
        return $user->can(PermissionEnum::ENROLLMENT_DELETE->value);
    }
}
