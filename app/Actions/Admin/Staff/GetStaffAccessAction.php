<?php

declare(strict_types=1);

namespace App\Actions\Admin\Staff;

use App\Models\Staff;
use Spatie\Permission\Models\Permission;

/**
 * Builds the access payload the admin panel uses for its client-side checks.
 *
 * A super admin passes every `Gate::before()` check in
 * {@see \App\Providers\AuthServiceProvider}, so its effective permission set is
 * the whole staff guard — the panel needs that list because it does not call the
 * Gate for navigation or button visibility. Everyone else gets the union of
 * their roles' permissions.
 *
 */
final readonly class GetStaffAccessAction
{
    /**
     * @return array{is_admin: bool, roles: list<string>, permissions: list<string>}
     */
    public function handle(Staff $staff): array
    {
        return [
            'is_admin'    => (bool) $staff->is_admin,
            'roles'       => $staff->getRoleNames()->values()->all(),
            'permissions' => $this->permissions($staff),
        ];
    }

    /**
     * @return list<string>
     */
    private function permissions(Staff $staff): array
    {
        if ($staff->is_admin) {
            return Permission::query()
                ->where('guard_name', 'staff')
                ->orderBy('name')
                ->pluck('name')
                ->all();
        }

        return $staff->getAllPermissions()
            ->pluck('name')
            ->sort()
            ->values()
            ->all();
    }
}
