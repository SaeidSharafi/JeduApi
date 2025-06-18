<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Data\User\UserCreateData;
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
            return $user->update($data->toArray())->fresh();
        });
    }
}
