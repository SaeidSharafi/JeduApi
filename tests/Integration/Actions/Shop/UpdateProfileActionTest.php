<?php

declare(strict_types=1);

use App\Actions\Shop\UpdateProfileAction;
use App\Data\Shop\Customer\UpdateProfileData;
use App\Enums\User\CivilIdTypeEnum;
use App\Enums\User\EducationLevelEnum;
use App\Enums\User\EducationStatusEnum;
use App\Enums\User\GenderEnum;
use App\Events\ProfileCompletedEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;

uses(Tests\Support\Traits\AuthTestTrait::class);

beforeEach(function (): void {
    $this->action = app(UpdateProfileAction::class);
});

function profileData(array $overrides = []): UpdateProfileData
{
    return new UpdateProfileData(
        first_name: array_key_exists('first_name', $overrides) ? $overrides['first_name'] : 'John',
        last_name: array_key_exists('last_name', $overrides) ? $overrides['last_name'] : 'Doe',
        email: array_key_exists('email', $overrides) ? $overrides['email'] : 'john@example.com',
        phone2: null,
        civil_id: array_key_exists('civil_id', $overrides) ? $overrides['civil_id'] : '1234567890',
        civil_id_type: array_key_exists('civil_id_type', $overrides) ? $overrides['civil_id_type'] : CivilIdTypeEnum::NATIONAL_CODE->value,
        date_of_birth: array_key_exists('date_of_birth', $overrides) ? $overrides['date_of_birth'] : Carbon::parse('1990-01-01'),
        father_name: array_key_exists('father_name', $overrides) ? $overrides['father_name'] : 'Father',
        gender: array_key_exists('gender', $overrides) ? $overrides['gender'] : GenderEnum::MALE->value,
        education_level: EducationLevelEnum::BACHELOR->value,
        field_of_study: 'Computer Science',
        education_status: EducationStatusEnum::GRADUATED->value,
    );
}

describe('UpdateProfileAction profile-completed event', function (): void {
    it('fires ProfileCompletedEvent the first time a customer completes their profile', function (): void {
        Event::fake([ProfileCompletedEvent::class]);

        $user = User::factory()->create();
        $user->update([
            'first_name'  => null,
            'last_name'   => null,
            'civil_id'    => null,
            'father_name' => null,
        ]);

        $this->action->handle(profileData(), $user->fresh());

        Event::assertDispatched(ProfileCompletedEvent::class, fn (ProfileCompletedEvent $event): bool => $event->user->id === $user->id);
    });

    it('does not fire when the profile is still incomplete', function (): void {
        Event::fake([ProfileCompletedEvent::class]);

        $user = User::factory()->create();
        $user->update(['email' => null]);

        $data = profileData(['email' => null]);

        $this->action->handle($data, $user->fresh());

        Event::assertNotDispatched(ProfileCompletedEvent::class);
    });

    it('does not re-fire when an already-complete profile is updated', function (): void {
        Event::fake([ProfileCompletedEvent::class]);

        $user = User::factory()->create(); // factory users are profile-complete

        $this->action->handle(profileData(), $user);

        Event::assertNotDispatched(ProfileCompletedEvent::class);
    });

    it('fires exactly once across repeated updates after completion', function (): void {
        Event::fake([ProfileCompletedEvent::class]);

        $user = User::factory()->create();
        $user->update([
            'first_name'  => null,
            'last_name'   => null,
            'civil_id'    => null,
            'father_name' => null,
        ]);

        $user = $user->fresh();
        $this->action->handle(profileData(), $user);
        $this->action->handle(profileData(['first_name' => 'Jane']), $user->fresh());

        Event::assertDispatchedTimes(ProfileCompletedEvent::class, 1);
    });
});
