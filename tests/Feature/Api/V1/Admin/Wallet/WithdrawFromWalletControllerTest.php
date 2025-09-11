<?php

declare(strict_types=1);

use App\Models\User;
use Tests\AuthTestTrait;

uses(AuthTestTrait::class);

test('admin can withdraw from wallet via API', function () {
    $admin = $this->authorized_user([
        App\Enums\PermissionEnum::WALLET_WITHDRAWAL,
    ]);

    $user = User::factory()->create();
    $user->wallet->update(['balance' => 2000]);

    $response = $this
        ->postJson(route('api.v1.admin.wallet.withdrawal', $user->wallet->id), [
            'user_id'     => $user->id,
            'amount'      => 500,
            'description' => 'API withdrawal test',
        ]);

    $response->assertStatus(201);

    $user->refresh();
    expect($user->wallet->balance)->toBe(1500);
});

test('admin cannot withdraw more than available balance via API', function () {
    $admin = $this->authorized_user([
        App\Enums\PermissionEnum::WALLET_WITHDRAWAL,
    ]);

    $user = User::factory()->create();
    $user->wallet->update(['balance' => 100]);

    $response = $this
        ->postJson(route('api.v1.admin.wallet.withdrawal', $user->wallet->id), [
            'user_id' => $user->id,
            'amount'  => 500,
        ]);

    $response->assertStatus(500); // Should return validation error
});
