<?php

declare(strict_types=1);

use App\Contracts\Cache\CacheStore;
use App\Enums\System\CacheKey;
use App\Models\PersonalAccessToken;

uses(Tests\Support\Traits\AuthTestTrait::class);

it('show profile', function (): void {
    $user     = App\Models\User::factory()->create();
    $response = $this->customer($user)
        ->getJson(route('api.v1.shop.profile.show'));
    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [
            'uuid',
            'first_name',
            'last_name',
            'email',
            'phone',
            'phone2',
            'civil_id',
            'civil_id_type',
            'date_of_birth',
            'father_name',
            'gender',
            'education_level',
            'field_of_study',
            'education_status',
        ],
    ]);

});

it('shows profile using the http only authentication cookie', function (): void {
    $user  = App\Models\User::factory()->create();
    $token = $user->createToken('auth_token')->plainTextToken;

    $this->withCredentials()
        ->withCookie('user_token', $token)
        ->getJson(route('api.v1.shop.profile.show'))
        ->assertOk()
        ->assertJsonPath('data.uuid', $user->uuid);
});

it('rejects unauthenticated profile requests', function (): void {
    $this->getJson(route('api.v1.shop.profile.show'))
        ->assertUnauthorized();
});

it('clears the cached tokenable when the customer avatar is deleted', function (): void {
    $user  = App\Models\User::factory()->create();
    $plain = $user->createToken('auth_token', ['*'], now()->addMinutes(60))->plainTextToken;
    $token = PersonalAccessToken::findToken($plain);
    $token->tokenable;

    $this->withToken($plain)
        ->deleteJson(route('api.v1.shop.profile.avatar.destroy'))
        ->assertNoContent();

    expect(app(CacheStore::class)->get(CacheKey::Tokenable, [
        'id'  => $token->id,
        'env' => app()->environment(),
    ]))->toBeNull();
});

it('clears the cached tokenable when the customer profile is updated', function (): void {
    $user  = App\Models\User::factory()->withPassport()->create();
    $plain = $user->createToken('auth_token', ['*'], now()->addMinutes(60))->plainTextToken;
    $token = PersonalAccessToken::findToken($plain);
    $token->tokenable;

    $this->withToken($plain)
        ->putJson(route('api.v1.shop.profile.update'), [
            'first_name'       => 'Updated',
            'last_name'        => 'Customer',
            'email'            => 'updated@example.com',
            'phone2'           => null,
            'civil_id'         => $user->civil_id,
            'civil_id_type'    => $user->civil_id_type->value,
            'date_of_birth'    => '1402-01-01',
            'father_name'      => 'Father Name',
            'gender'           => $user->gender->value,
            'education_level'  => $user->education_level->value,
            'field_of_study'   => 'Computer Science',
            'education_status' => $user->education_status->value,
        ])
        ->assertOk();

    expect(app(CacheStore::class)->get(CacheKey::Tokenable, [
        'id'  => $token->id,
        'env' => app()->environment(),
    ]))->toBeNull();
});

it('update all fields on newly created profile', function (): void {
    $user = App\Models\User::create([
        'phone' => '09123456789',
    ]);

    $response = $this->customer($user)
        ->putJson(route('api.v1.shop.profile.update'), [
            'first_name'       => 'John',
            'last_name'        => 'Doe',
            'email'            => 'john@example.com',
            'phone2'           => '09123456789',
            'civil_id'         => '1234567890',
            'civil_id_type'    => App\Enums\User\CivilIdTypeEnum::PASSPORT->value,
            'date_of_birth'    => '1402-01-01',
            'father_name'      => 'Father Name',
            'gender'           => App\Enums\User\GenderEnum::MALE->value,
            'education_level'  => App\Enums\User\EducationLevelEnum::BACHELOR->value,
            'field_of_study'   => 'Computer Science',
            'education_status' => App\Enums\User\EducationStatusEnum::GRADUATED->value,
        ]);
    $response->assertOk();
    $this->assertDatabaseHas('users', [
        'id'               => $user->id,
        'first_name'       => 'John',
        'last_name'        => 'Doe',
        'email'            => 'john@example.com',
        'phone2'           => '09123456789',
        'civil_id'         => '1234567890',
        'civil_id_type'    => App\Enums\User\CivilIdTypeEnum::PASSPORT->value,
        'date_of_birth'    => Hekmatinasser\Verta\Facades\Verta::parse('1402-01-01')->toCarbon(),
        'father_name'      => 'Father Name',
        'gender'           => App\Enums\User\GenderEnum::MALE->value,
        'education_level'  => App\Enums\User\EducationLevelEnum::BACHELOR->value,
        'field_of_study'   => 'Computer Science',
        'education_status' => App\Enums\User\EducationStatusEnum::GRADUATED->value,
    ]);

});
it('update all fields expcept civil id related fields when they are already filled', function (): void {
    $user = App\Models\User::create([
        'phone'         => '09123456789',
        'civil_id'      => '1122334455',
        'civil_id_type' => App\Enums\User\CivilIdTypeEnum::PASSPORT->value,
    ]);

    $response = $this->customer($user)
        ->putJson(route('api.v1.shop.profile.update'), [
            'first_name'       => 'John',
            'last_name'        => 'Doe',
            'email'            => 'john@example.com',
            'phone2'           => '09123456789',
            'civil_id'         => '12345678',
            'civil_id_type'    => App\Enums\User\CivilIdTypeEnum::IMMIGRANT_CODE->value,
            'date_of_birth'    => '1402-01-01',
            'father_name'      => 'Father Name',
            'gender'           => App\Enums\User\GenderEnum::MALE->value,
            'education_level'  => App\Enums\User\EducationLevelEnum::BACHELOR->value,
            'field_of_study'   => 'Computer Science',
            'education_status' => App\Enums\User\EducationStatusEnum::GRADUATED->value,
        ]);
    $response->assertOk();
    $this->assertDatabaseHas('users', [
        'id'               => $user->id,
        'first_name'       => 'John',
        'last_name'        => 'Doe',
        'email'            => 'john@example.com',
        'phone2'           => '09123456789',
        'civil_id'         => '1122334455',
        'civil_id_type'    => App\Enums\User\CivilIdTypeEnum::PASSPORT->value,
        'date_of_birth'    => Hekmatinasser\Verta\Facades\Verta::parse('1402-01-01')->toCarbon(),
        'father_name'      => 'Father Name',
        'gender'           => App\Enums\User\GenderEnum::MALE->value,
        'education_level'  => App\Enums\User\EducationLevelEnum::BACHELOR->value,
        'field_of_study'   => 'Computer Science',
        'education_status' => App\Enums\User\EducationStatusEnum::GRADUATED->value,
    ]);
    $this->assertDatabaseMissing('users', [
        'civil_id'      => '12345678',
        'civil_id_type' => App\Enums\User\CivilIdTypeEnum::IMMIGRANT_CODE->value,
    ]);
});
