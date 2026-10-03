<?php

declare(strict_types=1);

use App\Actions\Wallet\RecordWalletTransactionAction;
use App\Data\Admin\Wallet\RecordTransactionData;
use App\Enums\Wallet\TransactionSourceEnum;
use App\Enums\Wallet\TransactionTypeEnum;
use App\Exceptions\Wallet\WalletInsufficientBalanceException;
use App\Exceptions\Wallet\WalletNotActive;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\Date;
use Tests\Support\Traits\AuthTestTrait;

uses(AuthTestTrait::class);

test('admin with permission can record deposit transaction', function (): void {
    $admin = $this->admin_user();
    $user  = User::factory()->create();
    $data  = RecordTransactionData::from([
        'user_id'     => $user->id,
        'type'        => TransactionTypeEnum::DEPOSIT,
        'amount'      => 500,
        'source_type' => TransactionSourceEnum::STAFF,
        'source_id'   => null,
        'description' => 'Admin deposit',
        'metadata'    => [],
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

test('admin with permission can record gift transaction', function (): void {
    $admin = $this->admin_user();
    $user  = User::factory()->create();
    $data  = RecordTransactionData::from([
        'user_id'     => $user->id,
        'type'        => TransactionTypeEnum::GIFT,
        'amount'      => 500,
        'source_type' => TransactionSourceEnum::STAFF,
        'source_id'   => null,
        'description' => 'Admin deposit',
        'metadata'    => [],
    ]);
    $transaction = (new RecordWalletTransactionAction())->execute($data);
    expect($transaction)->toBeInstanceOf(WalletTransaction::class)
        ->and($transaction->amount)->toBe(500)
        ->and($transaction->type)->toBe(TransactionTypeEnum::GIFT)
        ->and($transaction->wallet_id)->toBe($user->wallet->id)
        ->and($transaction->user_id)->toBe($user->id);
    $user->wallet->refresh();
    expect($user->wallet->gift_balance)->toBe(500);
});
test('admin with permission can record bonus transaction', function (): void {
    $admin = $this->admin_user();
    $user  = User::factory()->create();
    $data  = RecordTransactionData::from([
        'user_id'     => $user->id,
        'type'        => TransactionTypeEnum::BONUS,
        'amount'      => 500,
        'source_type' => TransactionSourceEnum::STAFF,
        'source_id'   => null,
        'description' => 'Admin deposit',
        'metadata'    => [],
    ]);
    $transaction = (new RecordWalletTransactionAction())->execute($data);
    expect($transaction)->toBeInstanceOf(WalletTransaction::class)
        ->and($transaction->amount)->toBe(500)
        ->and($transaction->type)->toBe(TransactionTypeEnum::BONUS)
        ->and($transaction->wallet_id)->toBe($user->wallet->id)
        ->and($transaction->user_id)->toBe($user->id);
    $user->wallet->refresh();
    expect($user->wallet->gift_balance)->toBe(500);
});
test('cannot record transaction for invalid user', function (): void {
    $admin = $this->admin_user();
    $data  = RecordTransactionData::from([
        'user_id'     => 999999,
        'type'        => TransactionTypeEnum::DEPOSIT,
        'amount'      => 100,
        'source_type' => TransactionSourceEnum::STAFF,
        'source_id'   => null,
        'description' => 'Invalid user',
        'metadata'    => [],
    ]);
    expect(fn (): WalletTransaction => (new RecordWalletTransactionAction())->execute($data))
        ->toThrow(Exception::class, __('validation.custom.user_not_found'));
});

test('cannot record transaction for user without wallet', function (): void {
    $user = User::factory()->create();
    $user->wallet->delete(); // Ensure user has no wallet
    $data = RecordTransactionData::from([
        'user_id'     => $user->id,
        'type'        => TransactionTypeEnum::DEPOSIT,
        'amount'      => 100,
        'source_type' => TransactionSourceEnum::STAFF,
        'source_id'   => null,
        'description' => 'No wallet',
        'metadata'    => [],
    ]);
    expect(fn (): WalletTransaction => (new RecordWalletTransactionAction())->execute($data))
        ->toThrow(Exception::class, __('validation.custom.wallet_not_found'));
});

it('throws an error if tranaction amount is more than balance', function (): void {
    $user = User::factory()->create();

    $data = RecordTransactionData::from([
        'user_id'     => $user->id,
        'type'        => TransactionTypeEnum::WITHDRAWAL,
        'amount'      => -100,
        'source_type' => TransactionSourceEnum::STAFF,
        'source_id'   => null,
        'description' => 'No wallet',
        'metadata'    => [],
    ]);
    expect(fn (): WalletTransaction => (new RecordWalletTransactionAction())->execute($data))
        ->toThrow(WalletInsufficientBalanceException::class, __('validation.custom.insufficient_balance'));
});
it('will it automatically reduce balance if it\'s withdrawal', function (): void {
    $user = User::factory()->create();
    $user->wallet->update([
        'balance' => 200,
    ]);
    $data = RecordTransactionData::from([
        'user_id'     => $user->id,
        'type'        => TransactionTypeEnum::WITHDRAWAL,
        'amount'      => 100,
        'source_type' => TransactionSourceEnum::STAFF,
        'source_id'   => null,
        'description' => 'No wallet',
        'metadata'    => [],
    ]);
    $transaction = (new RecordWalletTransactionAction())->execute($data);
    expect($transaction)->toBeInstanceOf(WalletTransaction::class)
        ->and($transaction->amount)->toBe(-100)
        ->and($transaction->type)->toBe(TransactionTypeEnum::WITHDRAWAL)
        ->and($transaction->wallet_id)->toBe($user->wallet->id)
        ->and($transaction->user_id)->toBe($user->id);
    $user->wallet->refresh();
    expect($user->wallet->balance)->toBe(100);
});

it('will correctly set risk level', function (): void {
    $user = User::factory()->create();
    $user->wallet->update([
        'balance' => 100000000,
    ]);
    $data = RecordTransactionData::from([
        'user_id'     => $user->id,
        'type'        => TransactionTypeEnum::WITHDRAWAL,
        'amount'      => 60000000,
        'source_type' => TransactionSourceEnum::STAFF,
        'source_id'   => null,
        'description' => 'Admin deposit',
        'metadata'    => [],
    ]);
    $transaction = (new RecordWalletTransactionAction())->execute($data);
    expect($transaction)->toBeInstanceOf(WalletTransaction::class)
        ->and($transaction->metadata['audit']['risk_level'])->toBe('high');

    $user->wallet->update([
        'balance' => 100000000,
    ]);

    $data = RecordTransactionData::from([
        'user_id'     => $user->id,
        'type'        => TransactionTypeEnum::DEPOSIT,
        'amount'      => 6000000,
        'source_type' => TransactionSourceEnum::STAFF,
        'source_id'   => null,
        'description' => 'Admin deposit',
        'metadata'    => [],
    ]);

    $transaction = (new RecordWalletTransactionAction())->execute($data);
    expect($transaction)->toBeInstanceOf(WalletTransaction::class)
        ->and($transaction->metadata['audit']['risk_level'])->toBe('medium');

    $user->wallet->update([
        'balance' => 100000000,
    ]);

    $data = RecordTransactionData::from([
        'user_id'     => $user->id,
        'type'        => TransactionTypeEnum::WITHDRAWAL,
        'amount'      => 1000000,
        'source_type' => TransactionSourceEnum::STAFF,
        'source_id'   => null,
        'description' => 'Admin deposit',
        'metadata'    => [],
    ]);

    $transaction = (new RecordWalletTransactionAction())->execute($data);
    expect($transaction)->toBeInstanceOf(WalletTransaction::class)
        ->and($transaction->metadata['audit']['risk_level'])->toBe('medium');

    $testTime = now()->setTime(2, 0, 0);
    Date::setTestNow($testTime);

    $data = RecordTransactionData::from([
        'user_id'     => $user->id,
        'type'        => TransactionTypeEnum::WITHDRAWAL,
        'amount'      => 1000,
        'source_type' => TransactionSourceEnum::STAFF,
        'source_id'   => null,
        'description' => 'Admin deposit',
        'metadata'    => [],
    ]);
    $transaction = (new RecordWalletTransactionAction())->execute($data);
    expect($transaction)->toBeInstanceOf(WalletTransaction::class)
        ->and($transaction->metadata['audit']['risk_level'])->toBe('medium');
});

it('returns existing transaction when idempotency key is replayed', function (): void {
    $user = User::factory()->create();

    $data = RecordTransactionData::from([
        'user_id'         => $user->id,
        'type'            => TransactionTypeEnum::DEPOSIT,
        'amount'          => 1000,
        'source_type'     => TransactionSourceEnum::STAFF,
        'source_id'       => null,
        'description'     => 'Idempotent deposit',
        'metadata'        => [],
        'idempotency_key' => 'wallet-test-idempotency-key-1',
    ]);

    $first  = (new RecordWalletTransactionAction())->execute($data);
    $second = (new RecordWalletTransactionAction())->execute($data);

    expect($second->id)->toBe($first->id);
    expect(WalletTransaction::query()->where('idempotency_key', 'wallet-test-idempotency-key-1')->count())->toBe(1);

    $user->wallet->refresh();
    expect($user->wallet->balance)->toBe(1000);
});

it('blocks deposit when wallet is suspended', function (): void {
    $user = User::factory()->create();
    $user->wallet->update(['status' => App\Enums\Wallet\WalletStatusEnum::SUSPENDED]);

    $data = RecordTransactionData::from([
        'user_id'     => $user->id,
        'type'        => TransactionTypeEnum::DEPOSIT,
        'amount'      => 1000,
        'source_type' => TransactionSourceEnum::STAFF,
        'source_id'   => null,
        'description' => 'Suspended wallet deposit',
        'metadata'    => [],
    ]);

    expect(fn (): WalletTransaction => (new RecordWalletTransactionAction())->execute($data))
        ->toThrow(WalletNotActive::class);
});

it('allows refund when wallet is suspended', function (): void {
    $user = User::factory()->create();
    $user->wallet->update(['status' => App\Enums\Wallet\WalletStatusEnum::SUSPENDED]);

    $data = RecordTransactionData::from([
        'user_id'     => $user->id,
        'type'        => TransactionTypeEnum::REFUND,
        'amount'      => 1000,
        'source_type' => TransactionSourceEnum::ORDER,
        'source_id'   => 1,
        'description' => 'Suspended wallet refund',
        'metadata'    => [],
    ]);

    $transaction = (new RecordWalletTransactionAction())->execute($data);

    expect($transaction)->toBeInstanceOf(WalletTransaction::class);
    $user->wallet->refresh();
    expect($user->wallet->balance)->toBe(1000);
});
