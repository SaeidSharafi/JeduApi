<?php

declare(strict_types=1);

namespace App\Actions\Admin\Staff;

use App\Models\PersonalAccessToken;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;

final readonly class DeleteStaffAction
{
    /**
     * Execute the action.
     */
    public function handle(Staff $staff): void
    {
        $identifiers = DB::transaction(function () use ($staff): array {
            // Spatie auto-detaches roles and permissions on model deletion.
            // Sanctum tokens have no DB-level cascade and would otherwise linger.
            $identifiers = PersonalAccessToken::cacheIdentifiersFor($staff);

            $staff->tokens()->delete();
            $staff->delete();

            return $identifiers;
        });

        // Only once the delete is committed; see forgetCacheFor().
        PersonalAccessToken::forgetCachedIdentifiers($identifiers);
    }
}
