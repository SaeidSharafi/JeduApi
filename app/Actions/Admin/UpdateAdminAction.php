<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Data\Admin\CreateAdminData;
use App\Data\Admin\UpdateAdminData;
use App\Models\Admin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final readonly class UpdateAdminAction
{
    /**
     * Execute the action.
     */
    public function handle(UpdateAdminData $data, Admin $admin): void
    {
        DB::transaction(function () use ($admin, $data): void {
            $adminData = [
                'name'  => $data->name,
                'email' => $data->email,
                'phone' => $data->phone,
            ];
            if ($data->password) {
                $adminData['password'] = Hash::make($data->password);
            }
            $admin->update($adminData);

            if (!empty($data->roles)) {
                $admin->syncRoles($data->roles);
            }
        });
    }
}
