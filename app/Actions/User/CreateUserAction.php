<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Data\User\UserCreateData;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class CreateUserAction
{
    /**
     * Execute the action.
     */
    public function handle(UserCreateData $data): User
    {
        return DB::transaction(function () use($data): User {
            return User::create($data->toArray())->fresh();
        });
    }
}
