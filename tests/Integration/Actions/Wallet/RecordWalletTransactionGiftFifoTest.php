<?php

declare(strict_types=1);

use App\Actions\Wallet\RecordWalletTransactionAction;
use App\Data\Admin\Wallet\RecordTransactionData;
use App\Enums\Wallet\TransactionSourceEnum;
use App\Enums\Wallet\TransactionTypeEnum;
use App\Exceptions\Wallet\WalletInsufficientBalanceException;
use App\Models\User;
use App\Models\WalletTransaction;

it('records the full amount as remaining on a gift credit', function (): void {
    $user = User::factory()->create();

    $transaction = app(RecordWalletTransactionAction::class)->execute(
        RecordTransactionData::from([
            'user_id'     => $user->id,
            'type'        => TransactionTypeEnum::GIFT,
            'amount'      => 500,
            'source_type' => TransactionSourceEnum::PROMOTION,
            'metadata'    => [],
        ])
    );

    expect($transaction->remaining_amount)->toBe(500);

    $user->wallet->refresh();
    expect($user->wallet->gift_balance)->toBe(500);
});

it('consumes gift balance before normal balance on order payment', function (): void {
    $user   = User::factory()->create();
    $wallet = $user->wallet;
    $gift   = WalletTransaction::factory()->forWallet($wallet)->gift(500)->create();
    $wallet->update(['balance' => 1000, 'gift_balance' => 500]);

    $transaction = app(RecordWalletTransactionAction::class)->execute(
        RecordTransactionData::from([
            'user_id'     => $user->id,
            'type'        => TransactionTypeEnum::PAYMENT,
            'amount'      => 800,
            'source_type' => TransactionSourceEnum::ORDER,
            'source_id'   => 1,
            'metadata'    => [],
        ])
    );

    expect($transaction->balance_after)->toBe(700)
        ->and($transaction->gift_balance_after)->toBe(0);

    $wallet->refresh();
    expect($wallet->balance)->toBe(700)
        ->and($wallet->gift_balance)->toBe(0);

    $gift->refresh();
    expect($gift->remaining_amount)->toBe(0);

    $split = $transaction->metadata['audit']['wallet_debit_split'];
    expect($split['from_gift_balance'])->toBe(500)
        ->and($split['from_balance'])->toBe(300)
        ->and($split['gift_consumptions'])->toBe([
            ['transaction_id' => $gift->id, 'amount' => 500],
        ]);
});

it('consumes multiple gifts oldest first (FIFO by receipt)', function (): void {
    $user   = User::factory()->create();
    $wallet = $user->wallet;

    $olderGift = WalletTransaction::factory()->forWallet($wallet)->gift(300)
        ->create(['created_at' => now()->subDays(3)]);
    $newerGift = WalletTransaction::factory()->forWallet($wallet)->gift(500)
        ->create(['created_at' => now()->subDay()]);

    $wallet->update(['balance' => 1000, 'gift_balance' => 800]);

    $transaction = app(RecordWalletTransactionAction::class)->execute(
        RecordTransactionData::from([
            'user_id'     => $user->id,
            'type'        => TransactionTypeEnum::PAYMENT,
            'amount'      => 600,
            'source_type' => TransactionSourceEnum::ORDER,
            'source_id'   => 1,
            'metadata'    => [],
        ])
    );

    $olderGift->refresh();
    $newerGift->refresh();

    expect($olderGift->remaining_amount)->toBe(0)
        ->and($newerGift->remaining_amount)->toBe(200);

    $wallet->refresh();
    expect($wallet->balance)->toBe(1000)
        ->and($wallet->gift_balance)->toBe(200);

    $split = $transaction->metadata['audit']['wallet_debit_split'];
    expect($split['from_gift_balance'])->toBe(600)
        ->and($split['from_balance'])->toBe(0)
        ->and($split['gift_consumptions'])->toBe([
            ['transaction_id' => $olderGift->id, 'amount' => 300],
            ['transaction_id' => $newerGift->id, 'amount' => 300],
        ]);
});

it('partially consumes the oldest gift and tracks the remaining slice', function (): void {
    $user   = User::factory()->create();
    $wallet = $user->wallet;
    $gift   = WalletTransaction::factory()->forWallet($wallet)->gift(500)->create();
    $wallet->update(['balance' => 1000, 'gift_balance' => 500]);

    $transaction = app(RecordWalletTransactionAction::class)->execute(
        RecordTransactionData::from([
            'user_id'     => $user->id,
            'type'        => TransactionTypeEnum::PAYMENT,
            'amount'      => 200,
            'source_type' => TransactionSourceEnum::ORDER,
            'source_id'   => 1,
            'metadata'    => [],
        ])
    );

    $gift->refresh();
    expect($gift->remaining_amount)->toBe(300);

    $wallet->refresh();
    expect($wallet->gift_balance)->toBe(300)
        ->and($wallet->balance)->toBe(1000);

    $split = $transaction->metadata['audit']['wallet_debit_split'];
    expect($split['from_gift_balance'])->toBe(200)
        ->and($split['from_balance'])->toBe(0);
});

it('fully depletes gift balance without touching normal balance', function (): void {
    $user   = User::factory()->create();
    $wallet = $user->wallet;
    $gift   = WalletTransaction::factory()->forWallet($wallet)->gift(500)->create();
    $wallet->update(['balance' => 0, 'gift_balance' => 500]);

    $transaction = app(RecordWalletTransactionAction::class)->execute(
        RecordTransactionData::from([
            'user_id'     => $user->id,
            'type'        => TransactionTypeEnum::PAYMENT,
            'amount'      => 500,
            'source_type' => TransactionSourceEnum::ORDER,
            'source_id'   => 1,
            'metadata'    => [],
        ])
    );

    $gift->refresh();
    expect($gift->remaining_amount)->toBe(0);

    $wallet->refresh();
    expect($wallet->balance)->toBe(0)
        ->and($wallet->gift_balance)->toBe(0);

    $split = $transaction->metadata['audit']['wallet_debit_split'];
    expect($split['from_gift_balance'])->toBe(500)
        ->and($split['from_balance'])->toBe(0);
});

it('rejects order payment when combined funds are insufficient without mutating state', function (): void {
    $user   = User::factory()->create();
    $wallet = $user->wallet;
    $gift   = WalletTransaction::factory()->forWallet($wallet)->gift(300)->create();
    $wallet->update(['balance' => 200, 'gift_balance' => 300]);

    expect(fn (): WalletTransaction => app(RecordWalletTransactionAction::class)->execute(
        RecordTransactionData::from([
            'user_id'     => $user->id,
            'type'        => TransactionTypeEnum::PAYMENT,
            'amount'      => 600,
            'source_type' => TransactionSourceEnum::ORDER,
            'source_id'   => 1,
            'metadata'    => [],
        ])
    ))->toThrow(WalletInsufficientBalanceException::class);

    $wallet->refresh();
    expect($wallet->balance)->toBe(200)
        ->and($wallet->gift_balance)->toBe(300);

    $gift->refresh();
    expect($gift->remaining_amount)->toBe(300);
});

it('consumes untracked gift balance before normal balance', function (): void {
    $user = User::factory()->create();
    $user->wallet->update(['balance' => 1000, 'gift_balance' => 500]);

    $transaction = app(RecordWalletTransactionAction::class)->execute(
        RecordTransactionData::from([
            'user_id'     => $user->id,
            'type'        => TransactionTypeEnum::PAYMENT,
            'amount'      => 800,
            'source_type' => TransactionSourceEnum::ORDER,
            'source_id'   => 1,
            'metadata'    => [],
        ])
    );

    $user->wallet->refresh();
    expect($user->wallet->balance)->toBe(700)
        ->and($user->wallet->gift_balance)->toBe(0);

    $split = $transaction->metadata['audit']['wallet_debit_split'];
    expect($split['from_gift_balance'])->toBe(500)
        ->and($split['from_balance'])->toBe(300);
});

it('does not consume gifts again when an order payment is replayed', function (): void {
    $user   = User::factory()->create();
    $wallet = $user->wallet;
    $gift   = WalletTransaction::factory()->forWallet($wallet)->gift(500)->create();
    $wallet->update(['balance' => 1000, 'gift_balance' => 500]);

    $data = RecordTransactionData::from([
        'user_id'         => $user->id,
        'type'            => TransactionTypeEnum::PAYMENT,
        'amount'          => 800,
        'source_type'     => TransactionSourceEnum::ORDER,
        'source_id'       => 1,
        'metadata'        => [],
        'idempotency_key' => 'order-payment:1',
    ]);

    $first  = app(RecordWalletTransactionAction::class)->execute($data);
    $second = app(RecordWalletTransactionAction::class)->execute($data);

    expect($second->id)->toBe($first->id);

    $gift->refresh();
    expect($gift->remaining_amount)->toBe(0);

    $wallet->refresh();
    expect($wallet->balance)->toBe(700)
        ->and($wallet->gift_balance)->toBe(0);
});
