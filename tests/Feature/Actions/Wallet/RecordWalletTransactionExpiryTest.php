<?php

declare(strict_types=1);

use App\Actions\Wallet\RecordWalletTransactionAction;
use App\Data\Admin\Wallet\RecordTransactionData;
use App\Enums\Wallet\TransactionSourceEnum;
use App\Enums\Wallet\TransactionTypeEnum;
use App\Exceptions\Wallet\GiftAlreadyFullyReclaimedException;
use App\Exceptions\Wallet\GiftTransactionNotFoundException;
use App\Exceptions\Wallet\WalletNotActive;
use App\Models\User;
use App\Models\WalletTransaction;

it('reclaims a fully unspent expired gift via an EXPIRY debit', function (): void {
    $user   = User::factory()->create();
    $wallet = $user->wallet;
    $gift   = WalletTransaction::factory()->forWallet($wallet)->gift(500)->create();
    $wallet->update(['balance' => 1000, 'gift_balance' => 500]);

    $transaction = app(RecordWalletTransactionAction::class)->execute(
        RecordTransactionData::from([
            'user_id'             => $user->id,
            'type'                => TransactionTypeEnum::EXPIRY,
            'amount'              => 500,
            'source_type'         => TransactionSourceEnum::SYSTEM,
            'gift_transaction_id' => $gift->id,
            'description'         => 'Gift balance reclaimed after expiry',
            'idempotency_key'     => "wallet-gift-expiry:{$gift->id}",
        ])
    );

    expect($transaction->type)->toBe(TransactionTypeEnum::EXPIRY)
        ->and($transaction->amount)->toBe(-500)
        ->and($transaction->remaining_amount)->toBeNull()
        ->and($transaction->balance_after)->toBe(1000)
        ->and($transaction->gift_balance_after)->toBe(0);

    $gift->refresh();
    expect($gift->remaining_amount)->toBe(0);

    $wallet->refresh();
    expect($wallet->balance)->toBe(1000)
        ->and($wallet->gift_balance)->toBe(0);

    $split = $transaction->metadata['audit']['wallet_debit_split'];
    expect($split['from_gift_balance'])->toBe(500)
        ->and($split['from_balance'])->toBe(0)
        ->and($split['gift_consumptions'])->toBe([
            ['transaction_id' => $gift->id, 'amount' => 500],
        ]);
});

it('partially reclaims a gift when the amount is smaller than the remaining slice', function (): void {
    $user   = User::factory()->create();
    $wallet = $user->wallet;
    $gift   = WalletTransaction::factory()->forWallet($wallet)->gift(500)->create();
    $wallet->update(['balance' => 1000, 'gift_balance' => 500]);

    $transaction = app(RecordWalletTransactionAction::class)->execute(
        RecordTransactionData::from([
            'user_id'             => $user->id,
            'type'                => TransactionTypeEnum::EXPIRY,
            'amount'              => 200,
            'source_type'         => TransactionSourceEnum::SYSTEM,
            'gift_transaction_id' => $gift->id,
        ])
    );

    $gift->refresh();
    expect($gift->remaining_amount)->toBe(300);

    $wallet->refresh();
    expect($wallet->gift_balance)->toBe(300)
        ->and($wallet->balance)->toBe(1000);

    expect($transaction->amount)->toBe(-200)
        ->and($transaction->gift_balance_after)->toBe(300);
});

it('never reclaims more than the unspent remaining amount', function (): void {
    $user   = User::factory()->create();
    $wallet = $user->wallet;
    $gift   = WalletTransaction::factory()->forWallet($wallet)->gift(500)->create();
    $wallet->update(['balance' => 1000, 'gift_balance' => 500]);

    $transaction = app(RecordWalletTransactionAction::class)->execute(
        RecordTransactionData::from([
            'user_id'             => $user->id,
            'type'                => TransactionTypeEnum::EXPIRY,
            'amount'              => 5000,
            'source_type'         => TransactionSourceEnum::SYSTEM,
            'gift_transaction_id' => $gift->id,
        ])
    );

    $gift->refresh();
    expect($gift->remaining_amount)->toBe(0);

    $wallet->refresh();
    expect($wallet->gift_balance)->toBe(0);

    expect($transaction->amount)->toBe(-500);
});

it('throws when the gift is already fully spent', function (): void {
    $user   = User::factory()->create();
    $wallet = $user->wallet;
    $gift   = WalletTransaction::factory()->forWallet($wallet)->gift(500)->create();
    $wallet->update(['balance' => 1000, 'gift_balance' => 500]);

    // Fully consume the gift first.
    $gift->update(['remaining_amount' => 0]);
    $wallet->update(['gift_balance' => 0]);

    expect(fn () => app(RecordWalletTransactionAction::class)->execute(
        RecordTransactionData::from([
            'user_id'             => $user->id,
            'type'                => TransactionTypeEnum::EXPIRY,
            'amount'              => 500,
            'source_type'         => TransactionSourceEnum::SYSTEM,
            'gift_transaction_id' => $gift->id,
        ])
    ))->toThrow(GiftAlreadyFullyReclaimedException::class);

    expect(WalletTransaction::query()->where('type', TransactionTypeEnum::EXPIRY)->count())->toBe(0);

    $wallet->refresh();
    expect($wallet->gift_balance)->toBe(0);
});

it('rejects a gift transaction that does not belong to the wallet', function (): void {
    $user    = User::factory()->create();
    $wallet  = $user->wallet;
    $other   = User::factory()->create();
    $foreign = WalletTransaction::factory()->forWallet($other->wallet)->gift(500)->create();
    $wallet->update(['balance' => 1000, 'gift_balance' => 0]);

    expect(fn () => app(RecordWalletTransactionAction::class)->execute(
        RecordTransactionData::from([
            'user_id'             => $user->id,
            'type'                => TransactionTypeEnum::EXPIRY,
            'amount'              => 500,
            'source_type'         => TransactionSourceEnum::SYSTEM,
            'gift_transaction_id' => $foreign->id,
        ])
    ))->toThrow(GiftTransactionNotFoundException::class);

    $foreign->refresh();
    expect($foreign->remaining_amount)->toBe(500);
});

it('rejects a non-gift transaction as the reclaim target', function (): void {
    $user    = User::factory()->create();
    $wallet  = $user->wallet;
    $deposit = WalletTransaction::factory()->forWallet($wallet)->deposit(500)->create();
    $wallet->update(['balance' => 1000, 'gift_balance' => 0]);

    expect(fn () => app(RecordWalletTransactionAction::class)->execute(
        RecordTransactionData::from([
            'user_id'             => $user->id,
            'type'                => TransactionTypeEnum::EXPIRY,
            'amount'              => 500,
            'source_type'         => TransactionSourceEnum::SYSTEM,
            'gift_transaction_id' => $deposit->id,
        ])
    ))->toThrow(GiftTransactionNotFoundException::class);
});

it('does not reclaim twice when the same expiry key is replayed', function (): void {
    $user   = User::factory()->create();
    $wallet = $user->wallet;
    $gift   = WalletTransaction::factory()->forWallet($wallet)->gift(500)->create();
    $wallet->update(['balance' => 1000, 'gift_balance' => 500]);

    $data = RecordTransactionData::from([
        'user_id'             => $user->id,
        'type'                => TransactionTypeEnum::EXPIRY,
        'amount'              => 500,
        'source_type'         => TransactionSourceEnum::SYSTEM,
        'gift_transaction_id' => $gift->id,
        'idempotency_key'     => "wallet-gift-expiry:{$gift->id}",
    ]);

    $first  = app(RecordWalletTransactionAction::class)->execute($data);
    $second = app(RecordWalletTransactionAction::class)->execute($data);

    expect($second->id)->toBe($first->id);

    $gift->refresh();
    expect($gift->remaining_amount)->toBe(0);

    $wallet->refresh();
    expect($wallet->gift_balance)->toBe(0);

    expect(WalletTransaction::query()->where('type', TransactionTypeEnum::EXPIRY)->count())->toBe(1);
});

it('rejects expiry reclaims on a suspended wallet', function (): void {
    $user   = User::factory()->create();
    $wallet = $user->wallet;
    $gift   = WalletTransaction::factory()->forWallet($wallet)->gift(500)->create();
    $wallet->update(['balance' => 1000, 'gift_balance' => 500, 'status' => App\Enums\Wallet\WalletStatusEnum::SUSPENDED]);

    expect(fn () => app(RecordWalletTransactionAction::class)->execute(
        RecordTransactionData::from([
            'user_id'             => $user->id,
            'type'                => TransactionTypeEnum::EXPIRY,
            'amount'              => 500,
            'source_type'         => TransactionSourceEnum::SYSTEM,
            'gift_transaction_id' => $gift->id,
        ])
    ))->toThrow(WalletNotActive::class);
});
