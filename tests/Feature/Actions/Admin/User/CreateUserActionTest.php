<?php

declare(strict_types=1);

use App\Actions\Admin\User\CreateUserAction;
use App\Data\Admin\User\UserCreateData;
use App\Enums\User\CivilIdTypeEnum;
use App\Enums\User\EducationLevelEnum;
use App\Enums\User\EducationStatusEnum;
use App\Enums\User\GenderEnum;
use App\Models\User;
use Carbon\Carbon;
use Plank\Mediable\Media;

function userCreateData(array $overrides = []): UserCreateData
{
    return new UserCreateData(
        phone: $overrides['phone']                       ?? '09123456789',
        first_name: $overrides['first_name']             ?? 'John',
        last_name: $overrides['last_name']               ?? 'Doe',
        email: $overrides['email']                       ?? 'john@example.com',
        phone2: $overrides['phone2']                     ?? null,
        civil_id: $overrides['civil_id']                 ?? '1234567890',
        civil_id_type: $overrides['civil_id_type']       ?? CivilIdTypeEnum::NATIONAL_CODE->value,
        date_of_birth: $overrides['date_of_birth']       ?? Carbon::parse('1990-01-01'),
        father_name: $overrides['father_name']           ?? 'Father',
        gender: $overrides['gender']                     ?? GenderEnum::MALE->value,
        education_level: $overrides['education_level']   ?? EducationLevelEnum::BACHELOR->value,
        field_of_study: $overrides['field_of_study']     ?? 'Computer Science',
        education_status: $overrides['education_status'] ?? EducationStatusEnum::GRADUATED->value,
        media: $overrides['media']                       ?? ['avatar' => null],
    );
}

describe('CreateUserAction', function (): void {
    beforeEach(function (): void {
        $this->action = app(CreateUserAction::class);
    });

    it('creates a user from the DTO data excluding media', function (): void {
        $user = $this->action->handle(userCreateData());

        expect($user)->toBeInstanceOf(User::class);
        expect($user->id)->not->toBeNull();

        $fresh = $user->fresh();
        expect($fresh->first_name)->toBe('John');
        expect($fresh->last_name)->toBe('Doe');
        expect($fresh->email)->toBe('john@example.com');
        expect($fresh->phone)->toBe('09123456789');
        expect($fresh->civil_id)->toBe('1234567890');
        expect($fresh->father_name)->toBe('Father');
        expect($fresh->gender)->toBe(GenderEnum::MALE);
        expect($fresh->date_of_birth?->format('Y-m-d'))->toBe('1990-01-01');

        $this->assertDatabaseHas('users', [
            'id'         => $fresh->id,
            'first_name' => 'John',
            'last_name'  => 'Doe',
            'phone'      => '09123456789',
        ]);
    });

    it('creates a user without avatar media when no media id is provided', function (): void {
        $user = $this->action->handle(userCreateData());

        $fresh = $user->fresh();
        expect($fresh->avatar_url)->toBeNull();
        expect($fresh->getMedia('avatar'))->toBeEmpty();
        expect($fresh->media()->count())->toBe(0);
    });

    it('attaches the avatar media and stores its URL when a media id is provided', function (): void {
        $this->fakeMedia();
        $media = Media::query()->where('directory', 'fake-media')->first();

        $user = $this->action->handle(userCreateData(['media' => ['avatar' => $media->id]]));

        $fresh = $user->fresh();
        expect($fresh->avatar_url)->toBe($media->getUrl());
        expect($fresh->getMedia('avatar')->isNotEmpty())->toBeTrue();
    });
});
