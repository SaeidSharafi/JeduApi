<?php

declare(strict_types=1);

use App\Enums\PermissionEnum;
use App\Enums\Wallet\WalletStatusEnum;
use App\Models\User;
use Tests\Support\Traits\AuthTestTrait;

use function Pest\Laravel\postJson;

uses(AuthTestTrait::class);

test('admin with permission can create wallet via controller', function (): void {
    $admin = $this->authorized_user([PermissionEnum::WALLET_CREATE]);
    $user  = User::factory()->create();
    $user->wallet->delete(); // Ensure no existing wallet
    $payload = [
        'user_id'      => $user->id,
        'balance'      => 1000,
        'gift_balance' => 200,
        'status'       => WalletStatusEnum::ACTIVE->value,
    ];
    $response = postJson(route('api.v1.admin.wallet.create'), $payload);
    $response->assertCreated();
    $response->assertJsonPath('data.user_id', $user->id);
});

test('admin without permission cannot create wallet via controller', function (): void {
    $admin = $this->authorized_user([]); // No wallet.create permission
    $user  = User::factory()->create();
    $user->wallet->delete(); // Ensure no existing wallet
    $payload = [
        'user_id'      => $user->id,
        'balance'      => 1000,
        'gift_balance' => 200,
        'status'       => WalletStatusEnum::ACTIVE->value,
    ];
    $response = postJson(route('api.v1.admin.wallet.create'), $payload);
    $response->assertForbidden();
});

test('validation error on missing fields', function (): void {
    $admin   = $this->authorized_user([PermissionEnum::WALLET_CREATE]);
    $payload = [
        'balance' => 1000,
        'status'  => WalletStatusEnum::ACTIVE->value,
    ];
    $response = postJson(route('api.v1.admin.wallet.create'), $payload);
    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['user_id']);
});
