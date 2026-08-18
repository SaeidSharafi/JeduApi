<?php

declare(strict_types=1);

use App\Actions\Admin\WalletCampaign\AllocateBirthdayGiftsAction;
use App\Enums\Wallet\TransactionSourceEnum;
use App\Enums\Wallet\TransactionTypeEnum;
use App\Models\User;
use App\Models\WalletCampaign;
use App\Models\WalletTransaction;

/**
 * Number of birthday-gift transaction rows for the user from the campaign.
 */
function birthdayGiftRows(User $user, WalletCampaign $campaign): int
{
    return WalletTransaction::query()
        ->where('user_id', $user->id)
        ->where('source_type', TransactionSourceEnum::CAMPAIGN)
        ->where('source_id', $campaign->id)
        ->where('type', TransactionTypeEnum::GIFT)
        ->count();
}

function birthdaySweep(): array
{
    return app(AllocateBirthdayGiftsAction::class)->execute();
}

beforeEach(function (): void {
    $this->user = User::factory()->create([
        'date_of_birth' => now()->format('Y-m-d'),
    ]);

    $this->campaign = WalletCampaign::factory()->birthdayGift()->active()->create();
});

describe('AllocateBirthdayGiftsAction', function (): void {
    it('allocates a birthday gift to a customer whose birthday is today', function (): void {
        $result = birthdaySweep();

        expect($result)->toBe(['allocated' => 1, 'skipped' => 0]);

        expect(birthdayGiftRows($this->user, $this->campaign))->toBe(1);

        $gift = WalletTransaction::query()
            ->where('user_id', $this->user->id)
            ->where('source_type', TransactionSourceEnum::CAMPAIGN)
            ->where('source_id', $this->campaign->id)
            ->where('type', TransactionTypeEnum::GIFT)
            ->first();

        expect($gift)->not->toBeNull()
            ->and($gift->amount)->toBe($this->campaign->amount)
            ->and($gift->metadata['trigger_event'])->toBe('birthday_sweep')
            ->and($this->user->wallet->fresh()->gift_balance)->toBe($this->campaign->amount);
    });

    it('skips customers whose birthday is not today', function (): void {
        User::factory()->create([
            'date_of_birth' => now()->subDay()->format('Y-m-d'),
        ]);

        $result = birthdaySweep();

        expect($result)->toBe(['allocated' => 1, 'skipped' => 0]);

        $nonBirthdayUser = User::query()
            ->where('date_of_birth', now()->subDay()->format('Y-m-d'))
            ->first();

        expect(birthdayGiftRows($nonBirthdayUser, $this->campaign))->toBe(0);
    });

    it('skips customers without a date of birth', function (): void {
        User::factory()->create(['date_of_birth' => null]);

        $result = birthdaySweep();

        expect($result)->toBe(['allocated' => 1, 'skipped' => 0]);

        $noDobUser = User::query()->whereNull('date_of_birth')->first();

        expect(birthdayGiftRows($noDobUser, $this->campaign))->toBe(0);
    });

    it('does not double-allocate across re-runs', function (): void {
        $first  = birthdaySweep();
        $second = birthdaySweep();

        expect($first)->toBe(['allocated' => 1, 'skipped' => 0]);
        expect($second)->toBe(['allocated' => 0, 'skipped' => 0]);

        expect(birthdayGiftRows($this->user, $this->campaign))->toBe(1);

        $this->user->wallet->refresh();
        expect($this->user->wallet->gift_balance)->toBe($this->campaign->amount);
    });

    it('does not allocate from an inactive campaign', function (): void {
        $this->campaign->update(['is_active' => false]);

        $result = birthdaySweep();

        expect($result)->toBe(['allocated' => 0, 'skipped' => 0]);
        expect(birthdayGiftRows($this->user, $this->campaign))->toBe(0);
    });

    it('does not allocate from a campaign that has not started yet', function (): void {
        $this->campaign->update([
            'starts_at' => now()->addWeek(),
            'ends_at'   => now()->addMonth(),
        ]);

        $result = birthdaySweep();

        expect($result)->toBe(['allocated' => 0, 'skipped' => 0]);
        expect(birthdayGiftRows($this->user, $this->campaign))->toBe(0);
    });

    it('does not allocate from an expired campaign', function (): void {
        $this->campaign->update([
            'starts_at' => now()->subMonth(),
            'ends_at'   => now()->subWeek(),
        ]);

        $result = birthdaySweep();

        expect($result)->toBe(['allocated' => 0, 'skipped' => 0]);
        expect(birthdayGiftRows($this->user, $this->campaign))->toBe(0);
    });

    it('skips a campaign whose total usage limit is reached', function (): void {
        $this->campaign->update([
            'usage_limit_total' => 5,
            'total_usage_count' => 5,
        ]);

        $result = birthdaySweep();

        expect($result)->toBe(['allocated' => 0, 'skipped' => 1]);
        expect(birthdayGiftRows($this->user, $this->campaign))->toBe(0);
    });

    it('skips a campaign whose per-user usage limit is reached', function (): void {
        $this->campaign->update([
            'usage_limit_per_user' => 0,
        ]);

        $result = birthdaySweep();

        expect($result)->toBe(['allocated' => 0, 'skipped' => 1]);
        expect(birthdayGiftRows($this->user, $this->campaign))->toBe(0);
    });

    it('allocates from every active birthday campaign', function (): void {
        $secondCampaign = WalletCampaign::factory()->birthdayGift()->active()->create();

        $result = birthdaySweep();

        expect($result)->toBe(['allocated' => 2, 'skipped' => 0]);

        expect(birthdayGiftRows($this->user, $this->campaign))->toBe(1);
        expect(birthdayGiftRows($this->user, $secondCampaign))->toBe(1);
    });

    it('allocates to every eligible customer', function (): void {
        $secondUser = User::factory()->create([
            'date_of_birth' => now()->format('Y-m-d'),
        ]);

        $result = birthdaySweep();

        expect($result)->toBe(['allocated' => 2, 'skipped' => 0]);

        expect(birthdayGiftRows($this->user, $this->campaign))->toBe(1);
        expect(birthdayGiftRows($secondUser, $this->campaign))->toBe(1);
    });

    it('does not write anything in dry-run mode', function (): void {
        $result = app(AllocateBirthdayGiftsAction::class)->execute(dryRun: true);

        expect($result)->toBe(['allocated' => 1, 'skipped' => 0]);

        expect(birthdayGiftRows($this->user, $this->campaign))->toBe(0);
        expect($this->user->wallet->fresh()->gift_balance)->toBe(0);
    });

    it('does not fail when the user has no wallet', function (): void {
        $this->user->wallet->delete();
        $this->user->refresh();

        $result = birthdaySweep();

        expect($result)->toBe(['allocated' => 0, 'skipped' => 1]);
        expect(birthdayGiftRows($this->user, $this->campaign))->toBe(0);
    });

    it('does not allocate when no birthday campaign exists', function (): void {
        $this->campaign->delete();

        $result = birthdaySweep();

        expect($result)->toBe(['allocated' => 0, 'skipped' => 0]);
    });

    it('ignores non-birthday campaign types', function (): void {
        WalletCampaign::factory()->loyaltyReward()->active()->create();

        $result = birthdaySweep();

        expect($result)->toBe(['allocated' => 1, 'skipped' => 0]);
        expect(WalletTransaction::query()->where('type', TransactionTypeEnum::GIFT)->count())->toBe(1);
    });
});
