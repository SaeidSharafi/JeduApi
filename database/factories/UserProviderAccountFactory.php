<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ImportExport\UserProvisioningProviderEnum;
use App\Models\User;
use App\Models\UserProviderAccount;
use BackedEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UserProviderAccount> */
final class UserProviderAccountFactory extends Factory
{
    protected $model = UserProviderAccount::class;

    public function definition(): array
    {
        return [
            'user_id'         => User::factory(),
            'provider'        => UserProvisioningProviderEnum::MOODLE,
            'idempotency_key' => static fn (array $attributes): string => 'import-user:'.$attributes['user_id'].':'.($attributes['provider'] instanceof BackedEnum ? $attributes['provider']->value : $attributes['provider']),
            'status'          => 'succeeded',
        ];
    }
}
