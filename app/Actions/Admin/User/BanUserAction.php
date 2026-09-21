<?php

declare(strict_types=1);

namespace App\Actions\Admin\User;

use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class BanUserAction
{
    /**
     * Ban a customer: set the ban flag and instantly revoke all active tokens.
     */
    public function handle(User $user): User
    {
        $identifiers = DB::transaction(function () use ($user): array {
            $user->update([
                'is_banned' => true,
                'banned_at' => now(),
            ]);

            $identifiers = PersonalAccessToken::cacheIdentifiersFor($user);

            $user->tokens()->delete();

            return $identifiers;
        });

        // Only once the ban and the revoke are committed; see forgetCacheFor().
        PersonalAccessToken::forgetCachedIdentifiers($identifiers);

        return $user->fresh();
    }
}
