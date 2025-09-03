<?php

declare(strict_types=1);

use App\Actions\Admin\Wallet\DepositToWalletAction;
use App\Data\Wallet\DepositToWalletData;
use App\Enums\Wallet\WalletStatusEnum;
use App\Models\User;
use Tests\AuthTestTrait;

uses(AuthTestTrait::class);

test('deposit to wallet increases balance', function () {
    $user = User::factory()->create();
    $initialBalance = $user->wallet->balance;

    $admin = \App\Models\Staff::factory()->create()->fresh();

    $data = DepositToWalletData::from([
        'user_id' => $user->id,
        'amount' => 1000,
        'description' => 'Test deposit',
    ]);
    $action = app(DepositToWalletAction::class);
    $transaction = $action->execute($data,$admin);

    expect($transaction)->not->toBeNull()
        ->and($transaction->amount)->toBe(1000)
        ->and($transaction->balance_after)->toBe($initialBalance + 1000)
        ->and($user->fresh()->wallet->balance)->toBe($initialBalance + 1000);
});

test('cannot deposit to invalid user', function () {
    $admin = \App\Models\Staff::factory()->create()->fresh();

    $data = DepositToWalletData::from([
        'user_id' => 999999,
        'amount' => 1000,
    ]);

    expect(fn() => (app(DepositToWalletAction::class))->execute($data,$admin))
        ->toThrow(Exception::class, __('validation.user_not_found'));
});

test('cannot deposit to user without wallet', function () {
    $user = User::factory()->create();
    $user->wallet()->delete(); // Remove wallet

    $admin = \App\Models\Staff::factory()->create()->fresh();


    $data = DepositToWalletData::from([
        'user_id' => $user->id,
        'amount' => 1000,
    ]);

    expect(fn() => (app(DepositToWalletAction::class))->execute($data,$admin))
        ->toThrow(Exception::class, __('validation.wallet_not_found'));
});

test('cannot deposit to suspended wallet', function () {
    $user = User::factory()->create();
    $user->wallet->update(['status' => WalletStatusEnum::SUSPENDED]);

    $admin = \App\Models\Staff::factory()->create()->fresh();


    $data = DepositToWalletData::from([
        'user_id' => $user->id,
        'amount' => 1000,
    ]);

    expect(fn() => (app(DepositToWalletAction::class))->execute($data,$admin))
        ->toThrow(Exception::class, __('validation.wallet_not_active'));
});
