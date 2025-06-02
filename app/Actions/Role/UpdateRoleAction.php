<?php

declare(strict_types=1);

namespace App\Actions\Role;

use App\Data\Role\CreateRoleData;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

final readonly class UpdateRoleAction
{
    /**
     * Execute the action.
     */
    public function handle(CreateRoleData $data, Role $role): void
    {
        DB::transaction(function () use ($role, $data): void {
            $role->update([
                'name'  => $data->name,
                'label' => $data->label,
            ]);
            $role->syncPermissions($data->permissions);
        });
    }
}
