<?php

declare(strict_types=1);

namespace App\Actions\Admin\Role;

use App\Data\Admin\Role\CreateRoleData;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

final readonly class CreateRoleAction
{
    /**
     * Execute the action.
     */
    public function handle(CreateRoleData $data): void
    {
        DB::transaction(function () use ($data): void {
            $role = Role::query()->create([
                'name'  => $data->name,
                'label' => $data->label,
            ]);

            if (! empty($data->permissions)) {
                $role->syncPermissions($data->permissions);
            }
        });
    }
}
