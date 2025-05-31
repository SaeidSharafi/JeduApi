<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Data\Admin\CreateAdminData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final readonly class CreateAdminAction
{
    /**
     * Execute the action.
     */
    public function handle(CreateAdminData $data): void
    {
        DB::transaction(function () use ($data): void {
            $admin = \App\Models\Admin::create([
                'name'  => $data->name,
                'email' => $data->email,
                'phone' => $data->phone,
                'password' => Hash::make($data->password),
            ]);

            if (!empty($data->roles)) {
                $admin->syncRoles($data->roles);
            }

            // Optionally, you can log the creation or perform additional actions here
        });
    }
}
