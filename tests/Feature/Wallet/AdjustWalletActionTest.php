<?php

declare(strict_types=1);

use App\Actions\Wallet\AdjustWalletAction;
use App\Data\Wallet\AdjustWalletData;
use App\Enums\Wallet\WalletStatusEnum;
use App\Models\User;
use Tests\AuthTestTrait;

uses(AuthTestTrait::class);

test('positive adjustment increases wallet balance', function () {
    $user = User::factory()->create();
    $user->wallet->update(['balance' => 1000]);
    $initialBalance = $user->wallet->balance;

    $admin = $this->authorized_user([]);

    $data = AdjustWalletData::from([
        'user_id' => $user->id,
        'amount' => 500,
        'reason' => 'Dispute resolution - customer favor',
        'description' => 'Adjustment for service issue compensation',
    ]);

    $action = app(AdjustWalletAction::class);
    $transaction = $action->execute($data);

    expect($transaction)->not->toBeNull()
        ->and($transaction->amount)->toBe(500)
        ->and($transaction->type->value)->toBe('adjustment')
        ->and($transaction->balance_after)->toBe($initialBalance + 500)
        ->and($transaction->metadata)->toHaveKey('reason')
        ->and($transaction->metadata['reason'])->toBe('Dispute resolution - customer favor')
        ->and($transaction->metadata['adjustment_type'])->toBe('credit')
        ->and($user->fresh()->wallet->balance)->toBe($initialBalance + 500);
});

test('negative adjustment decreases wallet balance', function () {
    $user = User::factory()->create();
    $user->wallet->update(['balance' => 1000]);
    $initialBalance = $user->wallet->balance;

    $admin = $this->authorized_user([]);

    $data = AdjustWalletData::from([
        'user_id' => $user->id,
        'amount' => -300,
        'reason' => 'Error correction - overpayment',
        'description' => 'Adjustment for duplicate credit reversal',
    ]);

    $action = app(AdjustWalletAction::class);
    $transaction = $action->execute($data);

    expect($transaction)->not->toBeNull()
        ->and($transaction->amount)->toBe(-300)
        ->and($transaction->type->value)->toBe('adjustment')
        ->and($transaction->balance_after)->toBe($initialBalance - 300)
        ->and($transaction->metadata)->toHaveKey('reason')
        ->and($transaction->metadata['reason'])->toBe('Error correction - overpayment')
        ->and($transaction->metadata['adjustment_type'])->toBe('debit')
        ->and($user->fresh()->wallet->balance)->toBe($initialBalance - 300);
});

test('cannot make negative adjustment exceeding available balance', function () {
    $user = User::factory()->create();
    $user->wallet->update(['balance' => 100]);

    $admin = $this->authorized_user([]);

    $data = AdjustWalletData::from([
        'user_id' => $user->id,
        'amount' => -500, // More than available
        'reason' => 'Test insufficient funds',
    ]);

    expect(fn() => app(AdjustWalletAction::class)->execute($data))
        ->toThrow(Exception::class, __('validation.insufficient_balance'));
});

test('cannot adjust wallet for invalid user', function () {
    $admin = $this->authorized_user([]);

    $data = AdjustWalletData::from([
        'user_id' => 999999,
        'amount' => 100,
        'reason' => 'Invalid user test',
    ]);

    expect(fn() => app(AdjustWalletAction::class)->execute($data))
        ->toThrow(Exception::class, __('validation.user_not_found'));
});

test('cannot adjust suspended wallet', function () {
    $user = User::factory()->create();
    $user->wallet->update(['status' => WalletStatusEnum::SUSPENDED, 'balance' => 1000]);

    $admin = $this->authorized_user([]);

    $data = AdjustWalletData::from([
        'user_id' => $user->id,
        'amount' => 100,
        'reason' => 'Test suspended wallet',
    ]);

    expect(fn() => app(AdjustWalletAction::class)->execute($data))
        ->toThrow(Exception::class, __('validation.wallet_not_active'));
});
