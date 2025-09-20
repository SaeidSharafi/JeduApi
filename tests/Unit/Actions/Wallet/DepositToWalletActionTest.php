<?php

declare(strict_types=1);

use App\Actions\Admin\Wallet\DepositToWalletAction;
use App\Data\Admin\Wallet\DepositToWalletData;
use App\Enums\Wallet\WalletStatusEnum;
use App\Models\User;
use Tests\AuthTestTrait;

uses(AuthTestTrait::class);

test('deposit to wallet increases balance', function (): void {
    $user           = User::factory()->create();
    $initialBalance = $user->wallet->balance;

    $admin = App\Models\Staff::factory()->create()->fresh();

    $data = DepositToWalletData::from([

        'amount'      => 1000,
        'description' => 'Test deposit',
    ]);
    $action      = app(DepositToWalletAction::class);
    $transaction = $action->handle($data, $admin, $user->wallet);

    expect($transaction)->not->toBeNull()
        ->and($transaction->amount)->toBe(1000)
        ->and($transaction->balance_after)->toBe($initialBalance + 1000)
        ->and($user->fresh()->wallet->balance)->toBe($initialBalance + 1000);
});

test('cannot deposit to suspended wallet', function (): void {
    $user = User::factory()->create();
    $user->wallet->update(['status' => WalletStatusEnum::SUSPENDED]);

    $admin = App\Models\Staff::factory()->create()->fresh();

    $data = DepositToWalletData::from([

        'amount' => 1000,
    ]);

    expect(fn () => (app(DepositToWalletAction::class))->handle($data, $admin, $user->wallet))
        ->toThrow(Exception::class, __('validation.custom.wallet_not_active'));
});
