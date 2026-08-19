<?php

declare(strict_types=1);

namespace App\Actions\Admin\Staff;

use App\Models\Staff;
use Illuminate\Support\Facades\DB;

final readonly class BanStaffAction
{
    /**
     * Ban a staff account: set the ban flag and instantly revoke all active tokens.
     */
    public function handle(Staff $staff): Staff
    {
        return DB::transaction(function () use ($staff): Staff {
            $staff->update([
                'is_banned' => true,
                'banned_at' => now(),
            ]);

            $staff->tokens()->delete();

            return $staff->fresh();
        });
    }
}
