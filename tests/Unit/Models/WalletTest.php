<?php

declare(strict_types=1);

use App\Enums\Wallet\WalletStatusEnum;
use App\Models\User;
use App\Models\Wallet;

test('wallet has proper relationships', function () {
    $user   = User::factory()->create();
    $wallet = $user->wallet; // Use the automatically created wallet

    expect($wallet->user)
        ->toBeInstanceOf(User::class)
        ->and($wallet->user->id)
        ->toBe($user->id)
        ->and($wallet->transactions)
        ->toBeInstanceOf(Illuminate\Database\Eloquent\Collection::class);
});

test('wallet casts work correctly', function () {
    $user   = User::factory()->create();
    $wallet = $user->wallet;

    // Update the wallet with specific values
    $wallet->update([
        'balance'      => 50000,
        'gift_balance' => 25000,
        'status'       => 'active',
    ]);

    expect($wallet->balance)
        ->toBeInt()
        ->toBe(50000)
        ->and($wallet->gift_balance)
        ->toBeInt()
        ->toBe(25000)
        ->and($wallet->status)
        ->toBeInstanceOf(WalletStatusEnum::class)
        ->toBe(WalletStatusEnum::ACTIVE);
});

test('wallet business logic methods work correctly', function () {
    $user   = User::factory()->create();
    $wallet = $user->wallet;

    $wallet->update([
        'balance'      => 50000,
        'gift_balance' => 25000,
        'status'       => WalletStatusEnum::ACTIVE,
    ]);

    expect($wallet->getAvailableBalance())
        ->toBe(75000)
        ->and($wallet->canWithdraw(30000))
        ->toBeTrue()
        ->and($wallet->canWithdraw(60000))
        ->toBeFalse()
        ->and($wallet->canSpend(70000))
        ->toBeTrue()
        ->and($wallet->canSpend(80000))
        ->toBeFalse()
        ->and($wallet->isActive())
        ->toBeTrue()
        ->and($wallet->isSuspended())
        ->toBeFalse()
        ->and($wallet->isClosed())
        ->toBeFalse();
});

test('suspended wallet cannot withdraw or spend', function () {
    $user   = User::factory()->create();
    $wallet = $user->wallet;

    $wallet->update([
        'balance'      => 50000,
        'gift_balance' => 25000,
        'status'       => WalletStatusEnum::SUSPENDED,
    ]);

    expect($wallet->canWithdraw(10000))
        ->toBeFalse()
        ->and($wallet->canSpend(10000))
        ->toBeFalse()
        ->and($wallet->isSuspended())
        ->toBeTrue();
});

test('user automatically gets wallet when created', function () {
    $user = User::factory()->create();

    expect($user->wallet)
        ->toBeInstanceOf(Wallet::class)
        ->and($user->wallet->balance)
        ->toBe(0)
        ->and($user->wallet->gift_balance)
        ->toBe(0)
        ->and($user->wallet->status)
        ->toBe(WalletStatusEnum::ACTIVE);
});

test('wallet business logic with different statuses', function () {
    // Create users and update their wallets with different statuses
    $activeUser   = User::factory()->create();
    $activeWallet = $activeUser->wallet;
    $activeWallet->update(['status' => WalletStatusEnum::ACTIVE, 'balance' => 100000]);

    $suspendedUser   = User::factory()->create();
    $suspendedWallet = $suspendedUser->wallet;
    $suspendedWallet->update(['status' => WalletStatusEnum::SUSPENDED, 'balance' => 100000]);

    $closedUser   = User::factory()->create();
    $closedWallet = $closedUser->wallet;
    $closedWallet->update(['status' => WalletStatusEnum::CLOSED, 'balance' => 100000]);

    expect($activeWallet->status)->toBe(WalletStatusEnum::ACTIVE)
        ->and($activeWallet->isActive())->toBeTrue()
        ->and($suspendedWallet->status)->toBe(WalletStatusEnum::SUSPENDED)
        ->and($suspendedWallet->isSuspended())->toBeTrue()
        ->and($closedWallet->status)->toBe(WalletStatusEnum::CLOSED)
        ->and($closedWallet->isClosed())->toBeTrue();
});
