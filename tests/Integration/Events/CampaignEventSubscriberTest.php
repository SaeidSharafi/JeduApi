<?php

declare(strict_types=1);

use App\Enums\Wallet\TransactionSourceEnum;
use App\Enums\Wallet\TransactionTypeEnum;
use App\Enums\WalletCampaign\CampaignTypeEnum;
use App\Events\ProfileCompletedEvent;
use App\Models\Staff;
use App\Models\User;
use App\Models\WalletCampaign;
use App\Models\WalletTransaction;
use App\Subscribers\CampaignEventSubscriber;
use Carbon\Carbon;

uses(Tests\Support\Traits\AuthTestTrait::class);

beforeEach(function (): void {
    $this->customer = User::factory()->create();
    $this->staff    = Staff::factory()->create();

    $this->subscriber = app(CampaignEventSubscriber::class);
});

function registrationBonusCampaign(array $overrides = []): WalletCampaign
{
    return WalletCampaign::factory()->create(array_merge([
        'type'                 => CampaignTypeEnum::REGISTRATION_BONUS,
        'amount'               => 50000,
        'is_active'            => true,
        'usage_limit_total'    => null,
        'usage_limit_per_user' => 1,
        'total_usage_count'    => 0,
        'starts_at'            => null,
        'ends_at'              => null,
        'threshold_scope'      => 'lifetime',
        'metadata'             => null,
        'created_by'           => Staff::factory()->create()->id,
    ], $overrides));
}

function campaignGiftRows(User $user, WalletCampaign $campaign): int
{
    return WalletTransaction::query()
        ->where('user_id', $user->id)
        ->where('source_type', TransactionSourceEnum::CAMPAIGN)
        ->where('source_id', $campaign->id)
        ->where('type', TransactionTypeEnum::GIFT)
        ->count();
}

describe('CampaignEventSubscriber', function (): void {
    it('allocates a gift from every active registration_bonus campaign on profile completion', function (): void {
        $campaignA = registrationBonusCampaign(['amount' => 50000]);
        $campaignB = registrationBonusCampaign(['amount' => 30000]);

        $this->subscriber->handleProfileCompleted(new ProfileCompletedEvent($this->customer));

        expect(campaignGiftRows($this->customer, $campaignA))->toBe(1)
            ->and(campaignGiftRows($this->customer, $campaignB))->toBe(1);

        $gift = $this->customer->wallet->transactions()
            ->where('source_id', $campaignA->id)
            ->first();
        expect((int) $gift->amount)->toBe(50000);
        expect($this->customer->wallet->fresh()->gift_balance)->toBe(80000);
    });

    it('skips inactive campaigns', function (): void {
        $campaign = registrationBonusCampaign(['is_active' => false]);

        $this->subscriber->handleProfileCompleted(new ProfileCompletedEvent($this->customer));

        expect(campaignGiftRows($this->customer, $campaign))->toBe(0);
    });

    it('skips campaigns outside their date window', function (): void {
        $notStarted = registrationBonusCampaign(['starts_at' => now()->addWeek()]);
        $expired    = registrationBonusCampaign(['ends_at' => now()->subDay()]);

        $this->subscriber->handleProfileCompleted(new ProfileCompletedEvent($this->customer));

        expect(campaignGiftRows($this->customer, $notStarted))->toBe(0)
            ->and(campaignGiftRows($this->customer, $expired))->toBe(0);
    });

    it('does not double-allocate on repeated profile-completed events (idempotent)', function (): void {
        $campaign = registrationBonusCampaign();

        $event = new ProfileCompletedEvent($this->customer);
        $this->subscriber->handleProfileCompleted($event);
        $this->subscriber->handleProfileCompleted($event);

        expect(campaignGiftRows($this->customer, $campaign))->toBe(1);
    });

    it('honors the per-user limit and total limit', function (): void {
        $perUserExhausted = registrationBonusCampaign(['usage_limit_per_user' => 0]);
        $totalExhausted   = registrationBonusCampaign(['usage_limit_total' => 1, 'total_usage_count' => 1]);

        $this->subscriber->handleProfileCompleted(new ProfileCompletedEvent($this->customer));

        expect(campaignGiftRows($this->customer, $perUserExhausted))->toBe(0)
            ->and(campaignGiftRows($this->customer, $totalExhausted))->toBe(0);
    });

    it('does not fail when the user has no wallet', function (): void {
        $campaign = registrationBonusCampaign();

        $user = User::factory()->create();
        $user->wallet->delete();
        $user->refresh();

        $this->subscriber->handleProfileCompleted(new ProfileCompletedEvent($user));

        expect(campaignGiftRows($user, $campaign))->toBe(0);
    });

    it('only reacts to registration_bonus campaigns', function (): void {
        $manual = WalletCampaign::factory()->create([
            'type'                 => CampaignTypeEnum::MANUAL_ALLOCATION,
            'amount'               => 50000,
            'is_active'            => true,
            'usage_limit_per_user' => 1,
            'starts_at'            => null,
            'ends_at'              => null,
            'threshold_scope'      => 'lifetime',
            'created_by'           => $this->staff->id,
        ]);

        $this->subscriber->handleProfileCompleted(new ProfileCompletedEvent($this->customer));

        expect(campaignGiftRows($this->customer, $manual))->toBe(0);
    });

    it('sets a gift expiry deadline from campaign config on allocation', function (): void {
        $campaign = registrationBonusCampaign(['metadata' => ['expiry_days' => 7]]);

        $this->subscriber->handleProfileCompleted(new ProfileCompletedEvent($this->customer));

        $gift = $this->customer->wallet->transactions()
            ->where('source_id', $campaign->id)
            ->firstOrFail();

        expect($gift->expires_at?->format('Y-m-d'))->toBe(now()->addDays(7)->format('Y-m-d'));
    });

    it('allocates end-to-end when the real event is dispatched (subscriber registered)', function (): void {
        $campaign = registrationBonusCampaign();

        // No Event::fake — the real dispatcher must route ProfileCompletedEvent
        // to the explicitly registered subscriber.
        ProfileCompletedEvent::dispatch($this->customer);

        expect(campaignGiftRows($this->customer, $campaign))->toBe(1);
    });

    it('allocates end-to-end through a real profile completion', function (): void {
        $campaign = registrationBonusCampaign();

        // Full chain, no Event::fake: UpdateProfileAction transitions the user
        // to profile-complete, dispatches ProfileCompletedEvent, and the real
        // registered subscriber routes it to the campaign allocation.
        $user = User::factory()->create();
        $user->update([
            'first_name'  => null,
            'last_name'   => null,
            'civil_id'    => null,
            'father_name' => null,
        ]);

        $data = new App\Data\Shop\Customer\UpdateProfileData(
            first_name: 'John',
            last_name: 'Doe',
            email: 'john@example.com',
            phone2: null,
            civil_id: '1234567890',
            civil_id_type: App\Enums\User\CivilIdTypeEnum::NATIONAL_CODE->value,
            date_of_birth: Carbon::parse('1990-01-01'),
            father_name: 'Father',
            gender: App\Enums\User\GenderEnum::MALE->value,
            education_level: App\Enums\User\EducationLevelEnum::BACHELOR->value,
            field_of_study: 'Computer Science',
            education_status: App\Enums\User\EducationStatusEnum::GRADUATED->value,
        );

        app(App\Actions\Shop\UpdateProfileAction::class)->handle($data, $user->fresh());

        expect(campaignGiftRows($user, $campaign))->toBe(1);
    });
});
