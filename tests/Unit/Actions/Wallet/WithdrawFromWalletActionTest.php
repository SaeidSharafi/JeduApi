<?php

declare(strict_types=1);

use App\Actions\Admin\Wallet\WithdrawFromWalletAction;
use App\Data\Admin\Wallet\WithdrawFromWalletData;
use App\Enums\Wallet\WalletStatusEnum;
use App\Models\User;
use Tests\AuthTestTrait;

uses(AuthTestTrait::class);

test('withdraw from wallet decreases balance', function () {
    $user = User::factory()->create();
    $user->wallet->update(['balance' => 2000]);
    $initialBalance = $user->wallet->balance;

    $admin = App\Models\Staff::factory()->create()->fresh();

    $data = WithdrawFromWalletData::from([

        'amount'      => 500,
        'description' => 'Test withdrawal',
    ]);

    $transaction = (app(WithdrawFromWalletAction::class))->handle($data, $admin, $user->wallet);

    expect($transaction)->not->toBeNull()
        ->and($transaction->amount)->toBe(-500)
        ->and($transaction->balance_after)->toBe($initialBalance - 500)
        ->and($user->fresh()->wallet->balance)->toBe($initialBalance - 500);
});

test('cannot withdraw more than available balance', function () {
    $user = User::factory()->create();
    $user->wallet->update(['balance' => 100]);

    $admin = App\Models\Staff::factory()->create()->fresh();

    $data = WithdrawFromWalletData::from([

        'amount' => 500,
    ]);

    expect(fn () => (app(WithdrawFromWalletAction::class))->handle($data, $admin, $user->wallet))
        ->toThrow(Exception::class, __('validation.custom.insufficient_balance'));
});

test('cannot withdraw from suspended wallet', function () {
    $user = User::factory()->create();
    $user->wallet->update(['status' => WalletStatusEnum::SUSPENDED, 'balance' => 1000]);

    $admin = App\Models\Staff::factory()->create()->fresh();

    $data = WithdrawFromWalletData::from([

        'amount' => 100,
    ]);

    expect(fn () => (app(WithdrawFromWalletAction::class))->handle($data, $admin, $user->wallet))
        ->toThrow(Exception::class, __('validation.custom.wallet_not_active'));
});
