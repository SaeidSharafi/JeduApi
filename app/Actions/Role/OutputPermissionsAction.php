<?php

declare(strict_types=1);

namespace App\Actions\Role;

use App\Data\Role\CreateRoleData;
use App\Data\Role\PermissionData;
use Illuminate\Support\Facades\DB;
use Ramsey\Collection\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

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
                       'label'    => $group[0]['resource'],
                       'resource' => $key,
                       'permissions' => $group->toArray(),
                   ]
               ];
           })
           ->toArray();
    }
}
