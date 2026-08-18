<?php

declare(strict_types=1);

namespace App\Actions\Admin\User;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class BanUserAction
{
    /**
     * Ban a customer: set the ban flag and instantly revoke all active tokens.
     */
    public function handle(User $user): User
    {
        return DB::transaction(function () use ($user): User {
            $user->update([
                'is_banned' => true,
                'banned_at' => now(),
            ]);

            $user->tokens()->delete();

            return $user->fresh();
        });
    }
}
