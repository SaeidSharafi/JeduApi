<?php

declare(strict_types=1);

namespace App\Policies\Admin;

use App\Enums\PermissionEnum;
use App\Models\ContactUsRequest;
use App\Models\Staff;

final class ContactUsRequestPolicy
{
    public function viewAny(Staff $staff): bool
    {
        return $staff->can(PermissionEnum::CONTACT_US_REQUEST_VIEW_ANY->value);
    }

    public function view(Staff $staff, ContactUsRequest $request): bool
    {
        return $staff->can(PermissionEnum::CONTACT_US_REQUEST_VIEW->value);
    }

    public function update(Staff $staff, ContactUsRequest $request): bool
    {
        return $staff->can(PermissionEnum::CONTACT_US_REQUEST_UPDATE->value)
            || ($staff->can(PermissionEnum::CONTACT_US_REQUEST_UPDATE_OWN->value) && $request->assigned_to_id === $staff->id);
    }

    public function assign(Staff $staff, ContactUsRequest $request, ?Staff $assignee): bool
    {
        if ($staff->can(PermissionEnum::CONTACT_US_REQUEST_UPDATE->value)) {
            return true;
        }
        if (! $staff->can(PermissionEnum::CONTACT_US_REQUEST_UPDATE_OWN->value)) {
            return false;
        }

        return ($request->assigned_to_id === null && $assignee?->is($staff))
            || ($request->assigned_to_id === $staff->id && ($assignee === null || $assignee->is($staff)));
    }
}
