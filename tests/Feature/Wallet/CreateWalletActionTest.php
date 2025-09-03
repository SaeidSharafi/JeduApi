<?php

declare(strict_types=1);

use App\Actions\Admin\Wallet\CreateWalletAction;
use App\Data\Wallet\CreateWalletData;
use App\Enums\Wallet\WalletStatusEnum;
use App\Models\User;
use Tests\AuthTestTrait;

uses(AuthTestTrait::class);


test('wallet is auto-created for new user', function () {
    $user = User::factory()->create();
    expect($user->wallet)->not->toBeNull()
        ->and($user->wallet->user_id)->toBe($user->id)
        ->and($user->wallet->balance)->toBe(0)
        ->and($user->wallet->gift_balance)->toBe(0)
        ->and($user->wallet->status)->toBe(WalletStatusEnum::ACTIVE);
});

test('cannot create duplicate wallet for user', function () {
    $admin = $this->authorized_user([
        \App\Enums\PermissionEnum::WALLET_CREATE
    ]);
    $user = User::factory()->create();
    $data = CreateWalletData::from([
        'user_id' => $user->id,
        'balance' => 500,
        'gift_balance' => 0,
        'status' => WalletStatusEnum::ACTIVE->value,
    ]);
    expect(fn() => (new CreateWalletAction())->execute($data))
        ->toThrow(Exception::class, __('validation.wallet_already_exists'));
});

test('cannot create wallet for invalid user', function () {
    $admin = $this->authorized_user([
        \App\Enums\PermissionEnum::WALLET_CREATE
    ]);
    $data = CreateWalletData::from([
        'user_id' => 999999,
        'balance' => 100,
        'gift_balance' => 0,
        'status' => WalletStatusEnum::ACTIVE->value,
    ]);
    expect(fn() => (new CreateWalletAction())->execute($data))
        ->toThrow(Exception::class, __('validation.user_not_found'));
});
