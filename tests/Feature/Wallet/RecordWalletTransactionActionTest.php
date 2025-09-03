<?php

declare(strict_types=1);

use App\Actions\Wallet\RecordWalletTransactionAction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Enums\Wallet\TransactionTypeEnum;
use App\Enums\Wallet\TransactionSourceEnum;
use function Pest\Laravel\actingAs;
use Tests\AuthTestTrait;

uses(AuthTestTrait::class);

test('admin with permission can record deposit transaction', function () {
    $admin = $this->authorized_user([
        \App\Enums\PermissionEnum::WALLET_UPDATE
    ]);
    $user = User::factory()->create();
    $data = \App\Data\Wallet\RecordTransactionData::from([
        'user_id' => $user->id,
        'type' => TransactionTypeEnum::DEPOSIT,
        'amount' => 500,
        'source_type' => TransactionSourceEnum::STAFF,
        'source_id' => null,
        'description' => 'Admin deposit',
        'metadata' => [],
    ]);
    $transaction = (new RecordWalletTransactionAction())->execute($data);
    expect($transaction)->toBeInstanceOf(WalletTransaction::class)
        ->and($transaction->amount)->toBe(500)
        ->and($transaction->type)->toBe(TransactionTypeEnum::DEPOSIT)
        ->and($transaction->wallet_id)->toBe($user->wallet->id)
        ->and($transaction->user_id)->toBe($user->id);
    $user->wallet->refresh();
    expect($user->wallet->balance)->toBe(500);
});

test('cannot record transaction for invalid user', function () {
    $admin = $this->authorized_user([
        \App\Enums\PermissionEnum::WALLET_UPDATE
    ]);
    $data = \App\Data\Wallet\RecordTransactionData::from([
        'user_id' => 999999,
        'type' => TransactionTypeEnum::DEPOSIT,
        'amount' => 100,
        'source_type' => TransactionSourceEnum::STAFF,
        'source_id' => null,
        'description' => 'Invalid user',
        'metadata' => [],
    ]);
    expect(fn() => (new RecordWalletTransactionAction())->execute($data))
        ->toThrow(Exception::class, __('validation.user_not_found'));
});

test('cannot record transaction for user without wallet', function () {
    $admin = $this->authorized_user([
        \App\Enums\PermissionEnum::WALLET_UPDATE
    ]);
    $user = User::factory()->create();
    $user->wallet->delete(); // Ensure user has no wallet
    $data = \App\Data\Wallet\RecordTransactionData::from([
        'user_id' => $user->id,
        'type' => TransactionTypeEnum::DEPOSIT,
        'amount' => 100,
        'source_type' => TransactionSourceEnum::STAFF,
        'source_id' => null,
        'description' => 'No wallet',
        'metadata' => [],
    ]);
    expect(fn() => (new RecordWalletTransactionAction())->execute($data))
        ->toThrow(Exception::class, __('validation.wallet_not_found'));
});
