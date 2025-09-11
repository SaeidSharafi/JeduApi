<?php

declare(strict_types=1);

use App\Enums\Wallet\TransactionSourceEnum;
use App\Enums\Wallet\TransactionTypeEnum;
use App\Models\Order;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;

test('wallet transaction has proper relationships', function () {
    $user   = User::factory()->create();
    $wallet = $user->wallet;

    $transaction = WalletTransaction::factory()->create([
        'wallet_id' => $wallet->id,
        'user_id'   => $user->id,
    ]);

    expect($transaction->wallet)
        ->toBeInstanceOf(Wallet::class)
        ->and($transaction->user)
        ->toBeInstanceOf(User::class);
});

test('wallet transaction casts work correctly', function () {
    $user        = User::factory()->create();
    $wallet      = $user->wallet;
    $order       = Order::factory()->create(['customer_id' => $user->id]);
    $transaction = WalletTransaction::factory()->create([
        'wallet_id'          => $wallet->id,
        'user_id'            => $user->id,
        'amount'             => -50000,
        'balance_after'      => 25000,
        'gift_balance_after' => 10000,
        'type'               => 'payment',
        'source_type'        => TransactionSourceEnum::ORDER,
        'source_id'          => $order->id,
        'metadata'           => ['test' => 'data'],
    ]);

    expect($transaction->amount)
        ->toBeInt()
        ->toBe(-50000)
        ->and($transaction->balance_after)
        ->toBeInt()
        ->toBe(25000)
        ->and($transaction->gift_balance_after)
        ->toBeInt()
        ->toBe(10000)
        ->and($transaction->type)
        ->toBeInstanceOf(TransactionTypeEnum::class)
        ->toBe(TransactionTypeEnum::PAYMENT)
        ->and($transaction->source_type)
        ->toBeInstanceOf(TransactionSourceEnum::class)
        ->toBe(TransactionSourceEnum::ORDER)
        ->and($transaction->source->fresh())
        ->toBeInstanceOf(Order::class)
        ->and($transaction->source->fresh()->is($order->fresh()))
        ->toBeTrue()
        ->and($transaction->metadata)
        ->toBeArray()
        ->toBe(['test' => 'data']);
});

test('wallet transaction helper methods work correctly', function () {
    $user   = User::factory()->create();
    $wallet = $user->wallet;

    $creditTransaction = WalletTransaction::factory()->forWallet($wallet)->create(['amount' => 50000]);
    $debitTransaction  = WalletTransaction::factory()->forWallet($wallet)->create(['amount' => -30000]);
    $giftTransaction   = WalletTransaction::factory()->forWallet($wallet)->create([
        'type'       => TransactionTypeEnum::GIFT,
        'expires_at' => now()->addDays(30),
    ]);
    $expiredTransaction = WalletTransaction::factory()->forWallet($wallet)->create([
        'type'       => TransactionTypeEnum::BONUS,
        'expires_at' => now()->subDays(1),
    ]);

    expect($creditTransaction->isCredit())
        ->toBeTrue()
        ->and($creditTransaction->isDebit())
        ->toBeFalse()
        ->and($debitTransaction->isCredit())
        ->toBeFalse()
        ->and($debitTransaction->isDebit())
        ->toBeTrue()
        ->and($giftTransaction->isPromotional())
        ->toBeTrue()
        ->and($giftTransaction->isExpired())
        ->toBeFalse()
        ->and($expiredTransaction->isExpired())
        ->toBeTrue();
});

test('wallet transaction factory states work correctly', function () {
    $user   = User::factory()->create();
    $wallet = $user->wallet;

    $depositTransaction    = WalletTransaction::factory()->forWallet($wallet)->deposit(10000)->create();
    $withdrawalTransaction = WalletTransaction::factory()->forWallet($wallet)->withdrawal(5000)->create();
    $paymentTransaction    = WalletTransaction::factory()->forWallet($wallet)->payment(15000)->create();
    $refundTransaction     = WalletTransaction::factory()->forWallet($wallet)->refund(8000)->create();
    $giftTransaction       = WalletTransaction::factory()->forWallet($wallet)->gift(3000)->create();

    expect($depositTransaction->type)->toBe(TransactionTypeEnum::DEPOSIT)
        ->and($depositTransaction->amount)->toBe(10000)
        ->and($depositTransaction->source_type)->toBe(TransactionSourceEnum::STAFF)
        ->and($withdrawalTransaction->type)->toBe(TransactionTypeEnum::WITHDRAWAL)
        ->and($withdrawalTransaction->amount)->toBe(-5000)
        ->and($paymentTransaction->type)->toBe(TransactionTypeEnum::PAYMENT)
        ->and($paymentTransaction->amount)->toBe(-15000)
        ->and($paymentTransaction->source_type)->toBe(TransactionSourceEnum::ORDER)
        ->and($refundTransaction->type)->toBe(TransactionTypeEnum::REFUND)
        ->and($refundTransaction->amount)->toBe(8000)
        ->and($refundTransaction->source_type)->toBe(TransactionSourceEnum::ORDER)
        ->and($giftTransaction->type)->toBe(TransactionTypeEnum::GIFT)
        ->and($giftTransaction->amount)->toBe(3000)
        ->and($giftTransaction->source_type)->toBe(TransactionSourceEnum::PROMOTION)
        ->and($giftTransaction->expires_at)->not->toBeNull();
});
