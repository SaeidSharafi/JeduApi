<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Data\Admin\CreateAdminData;
use App\Data\Admin\UpdateAdminData;
use App\Models\Admin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final readonly class DeleteAdminAction
{
    /**
     * Execute the action.
     */
    public function handle( Admin $admin): void
    {
        DB::transaction(function () use ($admin): void {
            $admin->delete();
        });
    }
}
