<?php

declare(strict_types=1);

namespace App\Actions\Staff;

use App\Data\Admin\Staff\UpdateStaffData;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final readonly class UpdateStaffAction
{
    /**
     * Execute the action.
     */
    public function handle(UpdateStaffData $data, Staff $staff): void
    {
        DB::transaction(function () use ($staff, $data): void {
            $staffData = [
                'name'  => $data->name,
                'email' => $data->email,
                'phone' => $data->phone,
            ];
            if ($data->password) {
                $staffData['password'] = Hash::make($data->password);
            }
            $staff->update($staffData);

            if (! empty($data->roles)) {
                $staff->syncRoles($data->roles);
            }
        });
    }
}
