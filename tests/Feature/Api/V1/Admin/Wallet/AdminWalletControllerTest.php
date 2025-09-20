<?php

declare(strict_types=1);

use App\Enums\PermissionEnum;
use App\Models\User;
use Tests\AuthTestTrait;

use function Pest\Laravel\getJson;

uses(AuthTestTrait::class);

test('admin with permission can list wallets', function (): void {
    $admin = $this->authorized_user([PermissionEnum::WALLET_VIEW_ANY]);

    User::factory()->count(3)->create();
    $response = getJson(route('api.v1.admin.wallet.index'));
    $response->assertOk();
    $response->assertJsonCount(3, 'data.data');
});

test('admin without permission cannot list wallets', function (): void {
    $admin    = $this->authorized_user([]);
    $response = getJson(route('api.v1.admin.wallet.index'));
    $response->assertForbidden();
});

test('admin with permission can view a wallet', function (): void {
    $this->authorized_user([PermissionEnum::WALLET_VIEW]);
    $user   = User::factory()->create();
    $wallet = $user->wallet->fresh();

    $response = getJson(route('api.v1.admin.wallet.show', $wallet->id));
    $response->assertOk();
    $response->assertJsonPath('data.balance', $wallet->balance);
    $response->assertJsonPath('data.gift_balance', $wallet->gift_balance);
    $response->assertJsonPath('data.status.value', $wallet->status->value);
});
test('admin without permission cannot view a wallet', function (): void {
    $this->authorized_user([]);
    $user   = User::factory()->create();
    $wallet = $user->wallet;

    $response = getJson(route('api.v1.admin.wallet.show', [$wallet->id]));
    $response->assertForbidden();
});
