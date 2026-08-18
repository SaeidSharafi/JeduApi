<?php

declare(strict_types=1);

use App\Enums\Wallet\TransactionSourceEnum;
use App\Enums\Wallet\TransactionTypeEnum;
use App\Models\User;
use App\Models\WalletCampaign;
use App\Models\WalletTransaction;
use Carbon\Carbon;

use function Pest\Laravel\artisan;

const BIRTHDAY_SWEEP_FIXED_DATE = '2026-08-18 10:00:00';

function birthdaySweepUser(): User
{
    return User::factory()->create([
        'date_of_birth' => '1990-08-18',
    ]);
}

function birthdaySweepCampaign(): WalletCampaign
{
    return WalletCampaign::factory()->birthdayGift()->active()->create();
}

it('allocates birthday gifts when run via the command', function (): void {
    $this->travelTo(Carbon::parse(BIRTHDAY_SWEEP_FIXED_DATE));

    $user     = birthdaySweepUser();
    $campaign = birthdaySweepCampaign();

    artisan('wallet:allocate-birthday-gifts')
        ->expectsOutput('Checking for customers with a birthday today...')
        ->expectsOutput('Allocated 1 birthday gift(s).')
        ->assertExitCode(0);

    $gift = WalletTransaction::query()
        ->where('user_id', $user->id)
        ->where('source_type', TransactionSourceEnum::CAMPAIGN)
        ->where('source_id', $campaign->id)
        ->where('type', TransactionTypeEnum::GIFT)
        ->first();

    expect($gift)->not->toBeNull()
        ->and($gift->amount)->toBe($campaign->amount)
        ->and($user->wallet->fresh()->gift_balance)->toBe($campaign->amount);
});

it('reports when no birthday gifts can be allocated', function (): void {
    $this->travelTo(Carbon::parse(BIRTHDAY_SWEEP_FIXED_DATE));

    User::factory()->create(['date_of_birth' => '1990-01-01']); // birthday not today
    birthdaySweepCampaign();

    artisan('wallet:allocate-birthday-gifts')
        ->expectsOutput('Checking for customers with a birthday today...')
        ->expectsOutput('No birthday gifts to allocate.')
        ->assertExitCode(0);

    expect(WalletTransaction::query()->where('type', TransactionTypeEnum::GIFT)->count())->toBe(0);
});

it('is idempotent when run twice', function (): void {
    $this->travelTo(Carbon::parse(BIRTHDAY_SWEEP_FIXED_DATE));

    $user     = birthdaySweepUser();
    $campaign = birthdaySweepCampaign();

    artisan('wallet:allocate-birthday-gifts')->assertExitCode(0);

    artisan('wallet:allocate-birthday-gifts')
        ->expectsOutput('No birthday gifts to allocate.')
        ->assertExitCode(0);

    expect(WalletTransaction::query()
        ->where('user_id', $user->id)
        ->where('source_id', $campaign->id)
        ->where('type', TransactionTypeEnum::GIFT)
        ->count())->toBe(1);

    $user->wallet->refresh();
    expect($user->wallet->gift_balance)->toBe($campaign->amount);
});

it('does not write anything in dry-run mode', function (): void {
    $this->travelTo(Carbon::parse(BIRTHDAY_SWEEP_FIXED_DATE));

    $user = birthdaySweepUser();
    birthdaySweepCampaign();

    artisan('wallet:allocate-birthday-gifts --dry-run')
        ->expectsOutput('Checking for customers with a birthday today...')
        ->expectsOutput('Would allocate 1 birthday gift(s).')
        ->assertExitCode(0);

    expect(WalletTransaction::query()->where('type', TransactionTypeEnum::GIFT)->count())->toBe(0);

    $user->wallet->refresh();
    expect($user->wallet->gift_balance)->toBe(0);
});
