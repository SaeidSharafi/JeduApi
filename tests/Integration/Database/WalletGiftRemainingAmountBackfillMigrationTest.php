<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\Schema;

it('backfills remaining_amount distributing current gift balance FIFO', function (): void {
    $user   = User::factory()->create();
    $wallet = $user->wallet;

    // Older gift fully consumed, newer gift partially consumed under the old rule.
    $olderGift = WalletTransaction::factory()->forWallet($wallet)->gift(500)
        ->create(['created_at' => now()->subDays(5)]);
    $newerGift = WalletTransaction::factory()->forWallet($wallet)->gift(500)
        ->create(['created_at' => now()->subDays(2)]);

    $wallet->update(['gift_balance' => 300]);

    $migration = require database_path('migrations/2026_08_16_000001_add_remaining_amount_to_wallet_transactions_table.php');
    $migration->down();
    $migration->up();

    $olderGift->refresh();
    $newerGift->refresh();

    expect($olderGift->remaining_amount)->toBe(0)
        ->and($newerGift->remaining_amount)->toBe(300);
});

it('backfills zero remaining for gift credits of wallets with no gift balance', function (): void {
    $user   = User::factory()->create();
    $wallet = $user->wallet;

    $gift = WalletTransaction::factory()->forWallet($wallet)->gift(500)->create();
    $wallet->update(['gift_balance' => 0]);

    $migration = require database_path('migrations/2026_08_16_000001_add_remaining_amount_to_wallet_transactions_table.php');
    $migration->down();
    $migration->up();

    $gift->refresh();
    expect($gift->remaining_amount)->toBe(0);
});

it('keeps full remaining when gift balance exceeds the tracked credits', function (): void {
    $user   = User::factory()->create();
    $wallet = $user->wallet;

    // Untracked gift balance (e.g. initial gift_balance set at wallet creation).
    $gift = WalletTransaction::factory()->forWallet($wallet)->gift(500)->create();
    $wallet->update(['gift_balance' => 800]);

    $migration = require database_path('migrations/2026_08_16_000001_add_remaining_amount_to_wallet_transactions_table.php');
    $migration->down();
    $migration->up();

    $gift->refresh();
    expect($gift->remaining_amount)->toBe(500);
});

it('leaves non-gift transactions with a null remaining amount', function (): void {
    $user   = User::factory()->create();
    $wallet = $user->wallet;

    WalletTransaction::factory()->forWallet($wallet)->deposit(1000)->create();
    $wallet->update(['balance' => 1000]);

    $migration = require database_path('migrations/2026_08_16_000001_add_remaining_amount_to_wallet_transactions_table.php');
    $migration->down();
    $migration->up();

    expect(WalletTransaction::query()
        ->where('type', 'deposit')
        ->value('remaining_amount'))->toBeNull();

    expect(Schema::hasColumn('wallet_transactions', 'remaining_amount'))->toBeTrue();
});
