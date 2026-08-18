<?php

declare(strict_types=1);

namespace App\Actions\Admin\User;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class UnbanUserAction
{
    /**
     * Unban a customer: clear the ban flag and timestamp, restoring login.
     */
    public function handle(User $user): User
    {
        return DB::transaction(function () use ($user): User {
            $user->update([
                'is_banned' => false,
                'banned_at' => null,
            ]);

            return $user->fresh();
        });
    }
}
