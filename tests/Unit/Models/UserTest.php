<?php

declare(strict_types=1);

use App\Models\User;

test('to array', function (): void {
    $user = User::factory()->create()->fresh();
    expect($user->toArray())
        ->toEqual([
            'id'                => $user->id,
            'uuid'              => $user->uuid,
            'first_name'        => $user->first_name,
            'last_name'         => $user->last_name,
            'email'             => $user->email,
            'phone'             => $user->phone,
            'phone2'            => $user->phone2,
            'civil_id'          => $user->civil_id,
            'civil_id_type'     => $user->civil_id_type->value,
            'date_of_birth'     => $user->date_of_birth->format('Y-m-d'),
            'father_name'       => $user->father_name,
            'gender'            => $user->gender->value,
            'education_level'   => $user->education_level->value,
            'field_of_study'    => $user->field_of_study,
            'education_status'  => $user->education_status->value,
            'email_verified_at' => $user->email_verified_at?->utc()->toJSON(),
            'phone_verified_at' => $user->phone_verified_at?->utc()->toJSON(),
            'created_at'        => $user->created_at?->utc()?->toJSON(),
            'updated_at'        => $user->updated_at?->utc()?->toJSON(),
        ]);
});

it('check profile completion', function () {
    $user = User::create([
        'phone' => '0912365478',
    ])->fresh();

    expect($user->profileCompleted())->toBeFalse();

    $user = User::factory()->create()->fresh();
    expect($user->profileCompleted())->toBeTrue();
});

it('return teacher_data relationship', function () {
    $user    = User::factory()->create();
    $teacher = App\Models\Teacher::factory()->create([
        'user_id' => $user->id,
    ])->fresh();
    $user->load('teacherData');
    expect($user->teacherData->toArray())->toEqual($teacher->toArray());
});

it('return enrollments relationship', function () {
    $user      = User::factory()->create();
    $enrollment = App\Models\Enrollment::factory()->create([
        'customer_id' => $user->id,
    ])->fresh();
    $user->load('enrollments');
    expect($user->enrollments->first()->toArray())->toEqual($enrollment->toArray());
});
