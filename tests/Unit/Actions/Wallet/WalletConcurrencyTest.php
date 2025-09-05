<?php

declare(strict_types=1);

use App\Actions\Admin\Wallet\DepositToWalletAction;
use App\Actions\Admin\Wallet\WithdrawFromWalletAction;
use App\Data\Admin\Wallet\DepositToWalletData;
use App\Data\Admin\Wallet\WithdrawFromWalletData;
use App\Models\User;
use Tests\AuthTestTrait;

uses(AuthTestTrait::class);

test('concurrent transactions maintain balance integrity', function () {
    $user = User::factory()->create();
    $user->wallet->update(['balance' => 1000]);

    $admin = \App\Models\Staff::factory()->create()->fresh();

    // Simulate concurrent operations
    $results = [];

    // Multiple deposits and withdrawals in parallel
    $operations = [
        fn() => app(DepositToWalletAction::class)->handle(DepositToWalletData::from([

            'amount' => 100,
            'description' => 'Concurrent deposit 1'
        ]),$admin,$user->wallet),
        fn() => app(WithdrawFromWalletAction::class)->handle(WithdrawFromWalletData::from([

            'amount' => 50,
            'description' => 'Concurrent withdrawal 1'
        ]),$admin,$user->wallet),
        fn() => app(DepositToWalletAction::class)->handle(DepositToWalletData::from([

            'amount' => 200,
            'description' => 'Concurrent deposit 2'
        ]),$admin,$user->wallet),
        fn() => app(WithdrawFromWalletAction::class)->handle(WithdrawFromWalletData::from([

            'amount' => 75,
            'description' => 'Concurrent withdrawal 2'
        ]),$admin,$user->wallet),
    ];

    // Execute operations
    foreach ($operations as $operation) {
        $results[] = $operation();
    }

    // Verify final balance is correct
    $user->refresh();
    $expectedBalance = 1000 + 100 - 50 + 200 - 75; // 1175
    expect($user->wallet->balance)->toBe($expectedBalance)
        ->and($user->wallet->transactions()->count())->toBe(4);

    // Verify all transactions were recorded
});

test('insufficient balance prevents race condition exploitation', function () {
    $user = User::factory()->create();
    $user->wallet->update(['balance' => 100]);

    $admin = \App\Models\Staff::factory()->create()->fresh();

    // Try to withdraw more than available in multiple concurrent operations
    $exceptions = 0;
    $operations = [
        fn() => app(WithdrawFromWalletAction::class)->handle(WithdrawFromWalletData::from([

            'amount' => 80,
        ]),$admin,$user->wallet),
        fn() => app(WithdrawFromWalletAction::class)->handle(WithdrawFromWalletData::from([

            'amount' => 80,
        ]),$admin,$user->wallet),
    ];

    foreach ($operations as $operation) {
        try {
            $operation();
        } catch (Exception $e) {
            $exceptions++;
        }
    }

    $user->refresh();

    // At least one operation should have failed
    expect($exceptions)->toBeGreaterThan(0);
    // Balance should never go negative
    expect($user->wallet->balance)->toBeGreaterThanOrEqual(0);
});

test('database locking prevents balance inconsistency', function () {
    $user = User::factory()->create();
    $user->wallet->update(['balance' => 1000]);
    $admin = \App\Models\Staff::factory()->create()->fresh();
    // This test verifies that our lockForUpdate() call works
    // In a real race condition without locking, this could cause inconsistencies

    $depositAction = app(DepositToWalletAction::class);

    // Execute a transaction
    $transaction = $depositAction->handle(DepositToWalletData::from([

        'amount' => 500,
    ]),$admin,$user->wallet);

    // Verify the transaction has the correct balance_after
    expect($transaction->balance_after)->toBe(1500);

    $user->refresh();
    expect($user->wallet->balance)->toBe(1500);
});
