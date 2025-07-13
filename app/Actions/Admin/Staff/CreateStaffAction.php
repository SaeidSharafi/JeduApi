<?php

declare(strict_types=1);

namespace App\Actions\Admin\Staff;

use App\Data\Admin\Staff\CreateStaffData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final readonly class CreateStaffAction
{
    /**
     * Execute the action.
     */
    public function handle(CreateStaffData $data): void
    {
        DB::transaction(function () use ($data): void {
            $staff = \App\Models\Staff::create([
                'name'     => $data->name,
                'email'    => $data->email,
                'phone'    => $data->phone,
                'password' => Hash::make($data->password),
            ]);

            if (! empty($data->roles)) {
                $staff->syncRoles($data->roles);
            }

            // Optionally, you can log the creation or perform additional actions here
        });
    }
}
