<?php

declare(strict_types=1);

use App\Enums\User\CivilIdTypeEnum;
use App\Models\Teacher;
use App\Models\User;
use App\Services\Integrations\ImsService;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

uses(Tests\Support\Traits\AuthTestTrait::class);

beforeEach(function () {
    $this->user = User::factory()->create([
        'civil_id'      => '1234567890',
        'civil_id_type' => CivilIdTypeEnum::NATIONAL_CODE,
    ]);
    $this->teacher = Teacher::factory()->create([
        'user_id' => $this->user->id,
    ]);
    $this->courseCode = 'IMS-288-0002';

    $this->customer($this->user);
});

// ─── Index (GET) ────────────────────────────────────────────────────────────

it('index passes query parameters and returns service data', function () {
    $mockService = $this->mock(ImsService::class);
    $mockService->shouldReceive('getAttendance')
        ->once()
        ->with($this->courseCode, '1234567890', CivilIdTypeEnum::NATIONAL_CODE,Mockery::on(function ($payload) {
            return $payload['occurrence_id'] === 42;
        }))
        ->andReturn([
            'data' => ['attendance_date' => '2026-07-19', 'enrolments' => []],
        ]);

    getJson("/api/v1/shop/teacher/courses/{$this->courseCode}/attendances?occurrence_id=42")
        ->assertOk()
        ->assertJson([
            'data' => ['attendance_date' => '2026-07-19'],
        ]);
});

// ─── Store (POST) ───────────────────────────────────────────────────────────

it('store converts valid jalali date and forwards to IMS', function () {
    $mockService = $this->mock(ImsService::class);

    // 1405/01/01 in Jalali is 2026-03-21 in Gregorian
    $mockService->shouldReceive('storeAttendance')
        ->once()
        ->with($this->courseCode, '1234567890', CivilIdTypeEnum::NATIONAL_CODE, Mockery::on(function ($payload) {
            return $payload['attendance_date'] === '2026-03-21'
                && $payload['occurrence_id'] === 10
                && count($payload['attendances']) === 1;
        }))
        ->andReturn(['message' => 'Attendance stored']);

    postJson("/api/v1/shop/teacher/courses/{$this->courseCode}/attendances", [
        'attendance_date' => '1405/01/01', // Valid past Jalali Date
        'occurrence_id'   => 10,
        'attendances'     => [
            ['enrolment_id' => 1, 'attend_status' => -1],
        ],
    ])
        ->assertOk()
        ->assertJson(['message' => 'Attendance stored']);
});

it('store fails validation on invalid jalali date', function () {
    $this->mock(ImsService::class)->shouldNotReceive('storeAttendance');

    postJson("/api/v1/shop/teacher/courses/{$this->courseCode}/attendances", [
        // Bahman (11th month) only has 30 days
        'attendance_date' => '1405/11/31',
        'occurrence_id'   => null,
        'attendances'     => [],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['attendance_date', 'attendances']);
});

it('store fails validation if jalali date is in the future', function () {
    $this->mock(ImsService::class)->shouldNotReceive('storeAttendance');

    postJson("/api/v1/shop/teacher/courses/{$this->courseCode}/attendances", [
        // 1406/01/01 is March 2027 (Future date)
        'attendance_date' => '1406/01/01',
        'occurrence_id'   => null,
        'attendances'     => [
            ['enrolment_id' => 1, 'attend_status' => 1],
        ],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['attendance_date']);
});

it('store returns 422 when IMS validation fails', function () {
    $mockService = $this->mock(ImsService::class);
    $mockService->shouldReceive('storeAttendance')
        ->once()
        ->andThrow(new \App\Exceptions\Integrations\UnrecoverableProvisioningException(
            'Validation failed',
            422,
            null,
            ['validation_errors' => ['attendance_date' => ['Required']]],
        ));

    postJson("/api/v1/shop/teacher/courses/{$this->courseCode}/attendances", [
        'attendance_date' => '1405/01/01',
        'occurrence_id'   => null,
        'attendances'     => [
            ['enrolment_id' => 1, 'attend_status' => 1],
        ],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['attendance_date']);
});

// ─── Update (PUT) ───────────────────────────────────────────────────────────

it('update converts valid jalali date and forwards to IMS', function () {
    $mockService = $this->mock(ImsService::class);
    $mockService->shouldReceive('updateAttendance')
        ->once()
        ->with($this->courseCode, '1234567890', CivilIdTypeEnum::NATIONAL_CODE, Mockery::on(function ($payload) {
            return $payload['attendance_date'] === '2026-03-21';
        }))
        ->andReturn(['message' => 'Attendance updated']);

    putJson("/api/v1/shop/teacher/courses/{$this->courseCode}/attendances/1", [
        'attendance_date' => '1405/01/01',
        'occurrence_id'   => null,
        'attendances'     => [
            ['enrolment_id' => 1, 'attend_status' => 1],
        ],
    ])->assertOk();
});

it('update returns 422 when IMS validation fails', function () {
    $mockService = $this->mock(ImsService::class);
    $mockService->shouldReceive('updateAttendance')
        ->once()
        ->andThrow(new \App\Exceptions\Integrations\UnrecoverableProvisioningException(
            'Validation failed',
            422,
            null,
            ['validation_errors' => ['attendance_date' => ['Required']]],
        ));

    putJson("/api/v1/shop/teacher/courses/{$this->courseCode}/attendances/1", [
        'attendance_date' => '1405/01/01',
        'occurrence_id'   => null,
        'attendances'     => [
            ['enrolment_id' => 1, 'attend_status' => 1],
        ],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['attendance_date']);
});

// ─── Destroy (DELETE) ──────────────────────────────────────────────────────

it('destroy calls service and returns result', function () {
    $mockService = $this->mock(ImsService::class);
    $mockService->shouldReceive('destroyAttendance')
        ->once()
        ->with($this->courseCode, '1234567890', CivilIdTypeEnum::NATIONAL_CODE)
        ->andReturn(['message' => 'Attendance deleted']);

    deleteJson("/api/v1/shop/teacher/courses/{$this->courseCode}/attendances/1")
        ->assertOk()
        ->assertJson(['message' => 'Attendance deleted']);
});

// ─── Authentication ─────────────────────────────────────────────────────────

it('blocks unauthenticated users from attendance endpoints', function () {
    $this->app->get('auth')->forgetGuards();

    getJson("/api/v1/shop/teacher/courses/{$this->courseCode}/attendances")
        ->assertUnauthorized();

    postJson("/api/v1/shop/teacher/courses/{$this->courseCode}/attendances", [
        'attendance_date' => '1405/01/01',
        'occurrence_id'   => null,
        'attendances'     => [],
    ])->assertUnauthorized();

    putJson("/api/v1/shop/teacher/courses/{$this->courseCode}/attendances/1", [
        'attendance_date' => '1405/01/01',
        'occurrence_id'   => null,
        'attendances'     => [],
    ])->assertUnauthorized();

    deleteJson("/api/v1/shop/teacher/courses/{$this->courseCode}/attendances/1")
        ->assertUnauthorized();
});
