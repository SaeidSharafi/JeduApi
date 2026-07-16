<?php

declare(strict_types=1);

use App\Models\User;
use Tests\Support\Traits\AuthTestTrait;

uses(AuthTestTrait::class);

test('admin can withdraw from wallet via API', function (): void {
    $admin = $this->authorized_user([
        App\Enums\PermissionEnum::WALLET_WITHDRAWAL,
    ]);

    $user = User::factory()->create();
    $user->wallet->update(['balance' => 2000]);

    $response = $this
        ->postJson(route('api.v1.admin.users.wallet.withdrawal', $user->id), [
            'user_id'     => $user->id,
            'amount'      => 500,
            'description' => 'API withdrawal test',
        ]);

    $response->assertStatus(201);

    $user->refresh();
    expect($user->wallet->balance)->toBe(1500);
});

test('admin cannot withdraw more than available balance via API', function (): void {
    $admin = $this->authorized_user([
        App\Enums\PermissionEnum::WALLET_WITHDRAWAL,
    ]);

    $user = User::factory()->create();
    $user->wallet->update(['balance' => 100]);

    $response = $this
        ->postJson(route('api.v1.admin.users.wallet.withdrawal', $user->id), [
            'user_id' => $user->id,
            'amount'  => 500,
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('wallet_balance')
        ->assertJsonFragment([
            'metadata' => [
                'error_code'        => 'INSUFFICIENT_WALLET_BALANCE',
                'available_balance' => 100,
                'required_balance'  => 500,
                'shortfall'         => 400,
            ],
        ]);
});
