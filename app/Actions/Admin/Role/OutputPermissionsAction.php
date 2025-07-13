<?php

declare(strict_types=1);

namespace App\Actions\Admin\Role;

use App\Data\Admin\Role\PermissionData;
use Spatie\Permission\Models\Permission;

final readonly class OutputPermissionsAction
{
    /**
     * Execute the action.
     */
    public function handle(string $guard = 'staff'): array
    {
        $permissions = Permission::query()
            ->where('guard_name', $guard)->get();

        return collect(PermissionData::collect($permissions)->toArray())
            ->groupBy('resourceKey')
            ->mapWithKeys(function ($group) {
                $key = $group[0]['resourceKey'];

                return [
                    $key => [
                        'label'       => $group[0]['resource'],
                        'resource'    => $key,
                        'permissions' => $group->toArray(),
                    ],
                ];
            })
            ->toArray();
    }
}
