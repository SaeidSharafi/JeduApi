<?php

declare(strict_types=1);

use App\Enums\PermissionEnum;
use App\Enums\Wallet\WalletStatusEnum;
use App\Models\User;
use Tests\Support\Traits\AuthTestTrait;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

uses(AuthTestTrait::class);

test('admin with permission can view a wallet', function (): void {
    $this->authorized_user([PermissionEnum::WALLET_VIEW]);
    $user   = User::factory()->create();
    $wallet = $user->wallet->fresh();

    $response = getJson(route('api.v1.admin.users.wallet.show', $user->id));
    $response->assertOk();
    $response->assertJsonPath('data.balance', $wallet->balance);
    $response->assertJsonPath('data.gift_balance', $wallet->gift_balance);
    $response->assertJsonPath('data.status.value', $wallet->status->value);
});
test('admin without permission cannot view a wallet', function (): void {
    $this->authorized_user([]);
    $user   = User::factory()->create();
    $wallet = $user->wallet;

    $response = getJson(route('api.v1.admin.users.wallet.show', [$user->id]));
    $response->assertForbidden();
});
test('admin with permission can create wallet via controller', function (): void {
    $admin = $this->authorized_user([PermissionEnum::WALLET_CREATE]);
    $user  = User::factory()->create();
    $user->wallet->delete(); // Ensure no existing wallet
    $payload = [
        'balance'      => 1000,
        'gift_balance' => 200,
        'status'       => WalletStatusEnum::ACTIVE->value,
    ];
    $response = postJson(route('api.v1.admin.users.wallet.store', ['user' => $user->id]), $payload);
    $response->assertCreated();
    $response->assertJsonPath('data.balance', 1000);
});

test('admin without permission cannot create wallet via controller', function (): void {
    $admin = $this->authorized_user([]); // No wallet.create permission
    $user  = User::factory()->create();
    $user->wallet->delete(); // Ensure no existing wallet
    $payload = [
        'balance'      => 1000,
        'gift_balance' => 200,
        'status'       => WalletStatusEnum::ACTIVE->value,
    ];
    $response = postJson(route('api.v1.admin.users.wallet.store', ['user' => $user->id]), $payload);
    $response->assertForbidden();
});

test('validation error on missing fields', function (): void {
    $admin   = $this->authorized_user([PermissionEnum::WALLET_CREATE]);
    $user  = User::factory()->create();
    $payload = [
        'status'  => WalletStatusEnum::ACTIVE->value,
    ];
    $response = postJson(route('api.v1.admin.users.wallet.store', ['user' => $user->id]), $payload);
    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['balance']);

    $payload = [
        'balance'  => 0,
    ];
    $response = postJson(route('api.v1.admin.users.wallet.store', ['user' => $user->id]), $payload);
    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['status']);
});
