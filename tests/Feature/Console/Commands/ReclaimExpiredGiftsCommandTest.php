<?php

declare(strict_types=1);

use App\Enums\Wallet\TransactionTypeEnum;
use App\Models\User;
use App\Models\WalletTransaction;

use function Pest\Laravel\artisan;

it('reclaims expired gift balances when run via the command', function (): void {
    $user   = User::factory()->create();
    $wallet = $user->wallet;
    $gift   = WalletTransaction::factory()->forWallet($wallet)->gift(500)->expired()->create();
    $wallet->update(['balance' => 1000, 'gift_balance' => 500]);

    artisan('wallet:reclaim-expired-gifts')
        ->expectsOutput('Checking for expired gift balances...')
        ->expectsOutput('Reclaimed 1 gift(s).')
        ->assertExitCode(0);

    $gift->refresh();
    expect($gift->remaining_amount)->toBe(0);

    $wallet->refresh();
    expect($wallet->gift_balance)->toBe(0);

    expect(WalletTransaction::query()->where('type', TransactionTypeEnum::EXPIRY)->count())->toBe(1);
});

it('reports when no expired gifts are found', function (): void {
    User::factory()->create();

    artisan('wallet:reclaim-expired-gifts')
        ->expectsOutput('Checking for expired gift balances...')
        ->expectsOutput('No expired gift balances found.')
        ->assertExitCode(0);
});

it('is idempotent when run twice', function (): void {
    $user   = User::factory()->create();
    $wallet = $user->wallet;
    $gift   = WalletTransaction::factory()->forWallet($wallet)->gift(500)->expired()->create();
    $wallet->update(['balance' => 1000, 'gift_balance' => 500]);

    artisan('wallet:reclaim-expired-gifts')->assertExitCode(0);

    artisan('wallet:reclaim-expired-gifts')
        ->expectsOutput('No expired gift balances found.')
        ->assertExitCode(0);

    expect(WalletTransaction::query()->where('type', TransactionTypeEnum::EXPIRY)->count())->toBe(1);

    $wallet->refresh();
    expect($wallet->gift_balance)->toBe(0);
});

it('does not write anything in dry-run mode', function (): void {
    $user   = User::factory()->create();
    $wallet = $user->wallet;
    $gift   = WalletTransaction::factory()->forWallet($wallet)->gift(500)->expired()->create();
    $wallet->update(['balance' => 1000, 'gift_balance' => 500]);

    artisan('wallet:reclaim-expired-gifts --dry-run')
        ->expectsOutput('Checking for expired gift balances...')
        ->expectsOutput('Would reclaim 1 gift(s).')
        ->assertExitCode(0);

    expect(WalletTransaction::query()->where('type', TransactionTypeEnum::EXPIRY)->count())->toBe(0);

    $gift->refresh();
    expect($gift->remaining_amount)->toBe(500);

    $wallet->refresh();
    expect($wallet->gift_balance)->toBe(500);
});
