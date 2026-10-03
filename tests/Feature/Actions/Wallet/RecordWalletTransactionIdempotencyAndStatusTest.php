<?php

declare(strict_types=1);

use App\Actions\Wallet\RecordWalletTransactionAction;
use App\Data\Admin\Wallet\RecordTransactionData;
use App\Enums\Wallet\TransactionSourceEnum;
use App\Enums\Wallet\TransactionTypeEnum;
use App\Enums\Wallet\WalletStatusEnum;
use App\Exceptions\Wallet\WalletNotActive;
use App\Models\User;
use App\Models\WalletTransaction;

it('replays same idempotency key as no-op', function (): void {
    $user = User::factory()->create();

    $data = RecordTransactionData::from([
        'user_id'         => $user->id,
        'type'            => TransactionTypeEnum::DEPOSIT,
        'amount'          => 1000,
        'source_type'     => TransactionSourceEnum::STAFF,
        'source_id'       => null,
        'description'     => 'Idempotent ledger write',
        'metadata'        => [],
        'idempotency_key' => 'wallet-idempotency-test-1',
    ]);

    $action = app(RecordWalletTransactionAction::class);
    $first  = $action->execute($data);
    $second = $action->execute($data);

    expect($second->id)->toBe($first->id);
    expect(WalletTransaction::query()->where('idempotency_key', 'wallet-idempotency-test-1')->count())->toBe(1);

    $user->wallet->refresh();
    expect($user->wallet->balance)->toBe(1000);
});

it('rejects deposit for suspended wallet but allows refund', function (): void {
    $user = User::factory()->create();
    $user->wallet->update(['status' => WalletStatusEnum::SUSPENDED]);

    $action = app(RecordWalletTransactionAction::class);

    $depositData = RecordTransactionData::from([
        'user_id'     => $user->id,
        'type'        => TransactionTypeEnum::DEPOSIT,
        'amount'      => 1000,
        'source_type' => TransactionSourceEnum::STAFF,
        'source_id'   => null,
        'description' => 'Should fail while suspended',
        'metadata'    => [],
    ]);

    expect(fn (): WalletTransaction => $action->execute($depositData))->toThrow(WalletNotActive::class);

    $refundData = RecordTransactionData::from([
        'user_id'     => $user->id,
        'type'        => TransactionTypeEnum::REFUND,
        'amount'      => 1000,
        'source_type' => TransactionSourceEnum::ORDER,
        'source_id'   => 1,
        'description' => 'Allowed refund while suspended',
        'metadata'    => [],
    ]);

    $transaction = $action->execute($refundData);

    expect($transaction)->toBeInstanceOf(WalletTransaction::class);
    $user->wallet->refresh();
    expect($user->wallet->balance)->toBe(1000);
});
