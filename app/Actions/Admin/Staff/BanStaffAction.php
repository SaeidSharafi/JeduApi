<?php

declare(strict_types=1);

namespace App\Actions\Admin\Staff;

use App\Models\PersonalAccessToken;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;

final readonly class BanStaffAction
{
    /**
     * Ban a staff account: set the ban flag and instantly revoke all active tokens.
     */
    public function handle(Staff $staff): Staff
    {
        $identifiers = DB::transaction(function () use ($staff): array {
            $staff->update([
                'is_banned' => true,
                'banned_at' => now(),
            ]);

            $identifiers = PersonalAccessToken::cacheIdentifiersFor($staff);

            $staff->tokens()->delete();

            return $identifiers;
        });

        // Only once the ban and the revoke are committed; see forgetCacheFor().
        PersonalAccessToken::forgetCachedIdentifiers($identifiers);

        return $staff->fresh();
    }
}
