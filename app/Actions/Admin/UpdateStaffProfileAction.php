<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Data\Admin\Staff\UpdateStaffData;
use App\Data\Admin\UpdateStaffProfileData;
use App\Data\Shop\Customer\UpdateProfileData;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final readonly class UpdateStaffProfileAction
{
    /**
     * Execute the action.
     */
    public function handle(UpdateStaffProfileData $data, Staff $staff): void
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
        });
    }
}
