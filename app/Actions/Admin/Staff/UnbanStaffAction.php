<?php

declare(strict_types=1);

namespace App\Actions\Admin\Staff;

use App\Models\Staff;
use Illuminate\Support\Facades\DB;

final readonly class UnbanStaffAction
{
    /**
     * Unban a staff account: clear the ban flag and timestamp, restoring login.
     */
    public function handle(Staff $staff): Staff
    {
        return DB::transaction(function () use ($staff): Staff {
            $staff->update([
                'is_banned' => false,
                'banned_at' => null,
            ]);

            return $staff->fresh();
        });
    }
}
