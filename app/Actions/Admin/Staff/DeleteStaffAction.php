<?php

declare(strict_types=1);

namespace App\Actions\Admin\Staff;

use App\Models\Staff;
use Illuminate\Support\Facades\DB;

final readonly class DeleteStaffAction
{
    /**
     * Execute the action.
     */
    public function handle(Staff $staff): void
    {
        DB::transaction(function () use ($staff): void {
            // Spatie auto-detaches roles and permissions on model deletion.
            // Sanctum tokens have no DB-level cascade and would otherwise linger.
            $staff->tokens()->delete();
            $staff->delete();
        });
    }
}
