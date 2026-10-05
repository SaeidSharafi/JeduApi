<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserProviderAccount;

it('serializes its account identity and state', function (): void {
    $account = UserProviderAccount::factory()->create();

    expect(array_keys($account->toArray()))->toEqualCanonicalizing([
        'id', 'user_id', 'provider', 'status', 'idempotency_key', 'created_at', 'updated_at',
    ]);
});

it('belongs to its local user', function (): void {
    $user    = User::factory()->create();
    $account = UserProviderAccount::factory()->for($user)->create();

    expect($account->user)->toBeInstanceOf(User::class);
    expect($account->user->id)->toBe($user->id);
});
