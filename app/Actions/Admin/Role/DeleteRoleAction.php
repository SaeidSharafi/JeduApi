<?php

declare(strict_types=1);

namespace App\Actions\Admin\Role;

use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

final readonly class DeleteRoleAction
{
    /**
     * Execute the action.
     */
    public function handle(Role $role): void
    {
        DB::transaction(function () use ($role): void {
            $role->delete();
        });
    }
}
