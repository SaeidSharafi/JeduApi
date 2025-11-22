<?php

declare(strict_types=1);

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
            'civil_id_type'    => \App\Enums\User\CivilIdTypeEnum::PASSPORT->value,
            'date_of_birth'    => '1402-01-01',
            'father_name'      => 'Father Name',
            'gender'           => \App\Enums\User\GenderEnum::MALE->value,
            'education_level'  => \App\Enums\User\EducationLevelEnum::BACHELOR->value,
            'field_of_study'   => 'Computer Science',
            'education_status' => \App\Enums\User\EducationStatusEnum::GRADUATED->value,
        ]);
    $response->assertOk();
    $this->assertDatabaseHas('users', [
        'id'               => $user->id,
        'first_name'       => 'John',
        'last_name'        => 'Doe',
        'email'            => 'john@example.com',
        'phone2'           => '09123456789',
        'civil_id'         => '1234567890',
        'civil_id_type'    => \App\Enums\User\CivilIdTypeEnum::PASSPORT->value,
        'date_of_birth'    => Hekmatinasser\Verta\Facades\Verta::parse('1402-01-01')->toCarbon(),
        'father_name'      => 'Father Name',
        'gender'           => \App\Enums\User\GenderEnum::MALE->value,
        'education_level'  => \App\Enums\User\EducationLevelEnum::BACHELOR->value,
        'field_of_study'   => 'Computer Science',
        'education_status' => \App\Enums\User\EducationStatusEnum::GRADUATED->value,
    ]);

});
it('update all fields expcept civil id related fields when they are already filled', function (): void {
    $user = App\Models\User::create([
        'phone'         => '09123456789',
        'civil_id'      => '1122334455',
        'civil_id_type' => \App\Enums\User\CivilIdTypeEnum::PASSPORT->value,
    ]);

    $response = $this->customer($user)
        ->putJson(route('api.v1.shop.profile.update'), [
            'first_name'       => 'John',
            'last_name'        => 'Doe',
            'email'            => 'john@example.com',
            'phone2'           => '09123456789',
            'civil_id'         => '12345678',
            'civil_id_type'    => \App\Enums\User\CivilIdTypeEnum::IMMIGRANT_CODE->value,
            'date_of_birth'    => '1402-01-01',
            'father_name'      => 'Father Name',
            'gender'           => \App\Enums\User\GenderEnum::MALE->value,
            'education_level'  => \App\Enums\User\EducationLevelEnum::BACHELOR->value,
            'field_of_study'   => 'Computer Science',
            'education_status' => \App\Enums\User\EducationStatusEnum::GRADUATED->value,
        ]);
    $response->assertOk();
    $this->assertDatabaseHas('users', [
        'id'               => $user->id,
        'first_name'       => 'John',
        'last_name'        => 'Doe',
        'email'            => 'john@example.com',
        'phone2'           => '09123456789',
        'civil_id'         => '1122334455',
        'civil_id_type'    => \App\Enums\User\CivilIdTypeEnum::PASSPORT->value,
        'date_of_birth'    => Hekmatinasser\Verta\Facades\Verta::parse('1402-01-01')->toCarbon(),
        'father_name'      => 'Father Name',
        'gender'           => \App\Enums\User\GenderEnum::MALE->value,
        'education_level'  => \App\Enums\User\EducationLevelEnum::BACHELOR->value,
        'field_of_study'   => 'Computer Science',
        'education_status' => \App\Enums\User\EducationStatusEnum::GRADUATED->value,
    ]);
    $this->assertDatabaseMissing('users', [
        'civil_id'      => '12345678',
        'civil_id_type' => \App\Enums\User\CivilIdTypeEnum::IMMIGRANT_CODE->value,
    ]);
});
