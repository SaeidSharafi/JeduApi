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
            $staff->delete();
        });
    }
}
