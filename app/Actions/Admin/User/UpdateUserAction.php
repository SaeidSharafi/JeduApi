<?php

declare(strict_types=1);

namespace App\Actions\Admin\User;

use App\Data\Admin\User\UserCreateData;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class UpdateUserAction
{
    /**
     * Execute the action.
     */
    public function handle(UserCreateData $data, User $user): User
    {
        return DB::transaction(function () use ($data, $user): User {
            $user->update($data->toArray());

            return $user->fresh();
        });
    }
}
