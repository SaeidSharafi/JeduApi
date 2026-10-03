<?php

declare(strict_types=1);

use App\Actions\Wallet\ReclaimExpiredGiftsAction;
use App\Enums\Wallet\TransactionSourceEnum;
use App\Enums\Wallet\TransactionTypeEnum;
use App\Enums\Wallet\WalletStatusEnum;
use App\Models\User;
use App\Models\WalletTransaction;

it('reclaims an expired but unspent gift', function (): void {
    $user   = User::factory()->create();
    $wallet = $user->wallet;
    $gift   = WalletTransaction::factory()->forWallet($wallet)->gift(500)->expired()->create();
    $wallet->update(['balance' => 1000, 'gift_balance' => 500]);

    $result = app(ReclaimExpiredGiftsAction::class)->execute();

    expect($result)->toBe(['reclaimed' => 1, 'skipped' => 0]);

    $gift->refresh();
    expect($gift->remaining_amount)->toBe(0);

    $wallet->refresh();
    expect($wallet->gift_balance)->toBe(0)
        ->and($wallet->balance)->toBe(1000);

    $expiry = WalletTransaction::query()
        ->where('type', TransactionTypeEnum::EXPIRY)
        ->where('source_id', $gift->id)
        ->first();

    expect($expiry)->not->toBeNull()
        ->and($expiry->amount)->toBe(-500)
        ->and($expiry->gift_balance_after)->toBe(0)
        ->and($expiry->idempotency_key)->toBe("wallet-gift-expiry:{$gift->id}");
});

it('does not touch gifts that were fully spent before expiry', function (): void {
    $user   = User::factory()->create();
    $wallet = $user->wallet;
    $gift   = WalletTransaction::factory()->forWallet($wallet)->gift(500)->expired()->create();
    $wallet->update(['balance' => 1000, 'gift_balance' => 500]);

    // Fully consume the gift, as an order payment would.
    $gift->update(['remaining_amount' => 0]);
    $wallet->update(['gift_balance' => 0]);

    $result = app(ReclaimExpiredGiftsAction::class)->execute();

    expect($result)->toBe(['reclaimed' => 0, 'skipped' => 0]);

    expect(WalletTransaction::query()->where('type', TransactionTypeEnum::EXPIRY)->count())->toBe(0);

    $wallet->refresh();
    expect($wallet->gift_balance)->toBe(0);
});

it('is idempotent across repeated sweeps', function (): void {
    $user   = User::factory()->create();
    $wallet = $user->wallet;
    $gift   = WalletTransaction::factory()->forWallet($wallet)->gift(500)->expired()->create();
    $wallet->update(['balance' => 1000, 'gift_balance' => 500]);

    $first  = app(ReclaimExpiredGiftsAction::class)->execute();
    $second = app(ReclaimExpiredGiftsAction::class)->execute();

    expect($first)->toBe(['reclaimed' => 1, 'skipped' => 0]);
    expect($second)->toBe(['reclaimed' => 0, 'skipped' => 0]);

    expect(WalletTransaction::query()->where('type', TransactionTypeEnum::EXPIRY)->count())->toBe(1);

    $wallet->refresh();
    expect($wallet->gift_balance)->toBe(0);
});

it('skips a gift that still has remaining balance but already has an expiry transaction', function (): void {
    $user   = User::factory()->create();
    $wallet = $user->wallet;
    $gift   = WalletTransaction::factory()->forWallet($wallet)->gift(500)->expired()->create();
    $wallet->update(['balance' => 1000, 'gift_balance' => 500]);

    // Simulate an earlier reclaim that zeroed the balance but left a stale slice.
    WalletTransaction::factory()->forWallet($wallet)->create([
        'type'               => TransactionTypeEnum::EXPIRY,
        'amount'             => -500,
        'remaining_amount'   => null,
        'source_type'        => TransactionSourceEnum::SYSTEM,
        'source_id'          => $gift->id,
        'balance_after'      => 1000,
        'gift_balance_after' => 0,
        'idempotency_key'    => "wallet-gift-expiry:{$gift->id}",
        'expires_at'         => null,
    ]);

    $result = app(ReclaimExpiredGiftsAction::class)->execute();

    expect($result)->toBe(['reclaimed' => 0, 'skipped' => 0]);

    expect(WalletTransaction::query()->where('type', TransactionTypeEnum::EXPIRY)->count())->toBe(1);
});

it('does not reclaim gifts that have not expired yet', function (): void {
    $user   = User::factory()->create();
    $wallet = $user->wallet;
    $gift   = WalletTransaction::factory()->forWallet($wallet)->gift(500)->create([
        'expires_at' => now()->addDay(),
    ]);
    $wallet->update(['balance' => 1000, 'gift_balance' => 500]);

    $result = app(ReclaimExpiredGiftsAction::class)->execute();

    expect($result)->toBe(['reclaimed' => 0, 'skipped' => 0]);

    expect(WalletTransaction::query()->where('type', TransactionTypeEnum::EXPIRY)->count())->toBe(0);

    $gift->refresh();
    expect($gift->remaining_amount)->toBe(500);

    $wallet->refresh();
    expect($wallet->gift_balance)->toBe(500);
});

it('does not reclaim non-gift transactions', function (): void {
    $user   = User::factory()->create();
    $wallet = $user->wallet;
    WalletTransaction::factory()->forWallet($wallet)->deposit(500)->create([
        'expires_at' => now()->subDay(),
    ]);
    $wallet->update(['balance' => 1000, 'gift_balance' => 0]);

    $result = app(ReclaimExpiredGiftsAction::class)->execute();

    expect($result)->toBe(['reclaimed' => 0, 'skipped' => 0]);

    expect(WalletTransaction::query()->where('type', TransactionTypeEnum::EXPIRY)->count())->toBe(0);
});

it('reclaims expired bonus credits too', function (): void {
    $user   = User::factory()->create();
    $wallet = $user->wallet;
    $bonus  = WalletTransaction::factory()->forWallet($wallet)->bonus(300)->expired()->create();
    $wallet->update(['balance' => 1000, 'gift_balance' => 300]);

    $result = app(ReclaimExpiredGiftsAction::class)->execute();

    expect($result)->toBe(['reclaimed' => 1, 'skipped' => 0]);

    $bonus->refresh();
    expect($bonus->remaining_amount)->toBe(0);

    $wallet->refresh();
    expect($wallet->gift_balance)->toBe(0);
});

it('skips wallets that are not active', function (): void {
    $user   = User::factory()->create();
    $wallet = $user->wallet;
    $gift   = WalletTransaction::factory()->forWallet($wallet)->gift(500)->expired()->create();
    $wallet->update(['balance' => 1000, 'gift_balance' => 500, 'status' => WalletStatusEnum::SUSPENDED]);

    $result = app(ReclaimExpiredGiftsAction::class)->execute();

    expect($result)->toBe(['reclaimed' => 0, 'skipped' => 0]);

    expect(WalletTransaction::query()->where('type', TransactionTypeEnum::EXPIRY)->count())->toBe(0);

    $gift->refresh();
    expect($gift->remaining_amount)->toBe(500);
});

it('does not write anything in dry-run mode', function (): void {
    $user   = User::factory()->create();
    $wallet = $user->wallet;
    $gift   = WalletTransaction::factory()->forWallet($wallet)->gift(500)->expired()->create();
    $wallet->update(['balance' => 1000, 'gift_balance' => 500]);

    $result = app(ReclaimExpiredGiftsAction::class)->execute(dryRun: true);

    expect($result)->toBe(['reclaimed' => 1, 'skipped' => 0]);

    expect(WalletTransaction::query()->where('type', TransactionTypeEnum::EXPIRY)->count())->toBe(0);

    $gift->refresh();
    expect($gift->remaining_amount)->toBe(500);

    $wallet->refresh();
    expect($wallet->gift_balance)->toBe(500);
});

it('reclaims only the unspent remainder of a partially spent gift', function (): void {
    $user   = User::factory()->create();
    $wallet = $user->wallet;
    $gift   = WalletTransaction::factory()->forWallet($wallet)->gift(500)->expired()->create();
    $wallet->update(['balance' => 1000, 'gift_balance' => 500]);

    // Consume part of the gift, as an order payment would.
    $gift->update(['remaining_amount' => 200]);
    $wallet->update(['gift_balance' => 200]);

    $result = app(ReclaimExpiredGiftsAction::class)->execute();

    expect($result)->toBe(['reclaimed' => 1, 'skipped' => 0]);

    $gift->refresh();
    expect($gift->remaining_amount)->toBe(0);

    $wallet->refresh();
    expect($wallet->gift_balance)->toBe(0);

    $expiry = WalletTransaction::query()
        ->where('type', TransactionTypeEnum::EXPIRY)
        ->where('source_id', $gift->id)
        ->first();

    expect($expiry)->not->toBeNull()
        ->and($expiry->amount)->toBe(-200);
});
