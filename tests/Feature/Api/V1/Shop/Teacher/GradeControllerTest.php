<?php

declare(strict_types=1);

use App\Enums\User\CivilIdTypeEnum;
use App\Models\Teacher;
use App\Models\User;
use App\Services\Integrations\ImsService;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

uses(Tests\Support\Traits\AuthTestTrait::class);

beforeEach(function (): void {
    $this->user = User::factory()->create([
        'civil_id'      => '1234567890',
        'civil_id_type' => CivilIdTypeEnum::NATIONAL_CODE,
    ]);
    $this->teacher = Teacher::factory()->create([
        'user_id' => $this->user->id,
    ]);
    $this->courseCode = 'IMS-100';

    $this->customer($this->user);
});

// ─── Index (GET) ────────────────────────────────────────────────────────────

it('index returns grades list from IMS', function (): void {
    $mockService = $this->mock(ImsService::class);
    $mockService->shouldReceive('getGrades')
        ->once()
        ->with($this->courseCode, '1234567890', CivilIdTypeEnum::NATIONAL_CODE, Mockery::type('array'))
        ->andReturn(['data' => [['grade' => 95.5]]]);

    getJson("/api/v1/shop/teacher/courses/{$this->courseCode}/grades")
        ->assertOk()
        ->assertJsonPath('data.0.grade', 95.5);
});

it('index passes query parameters to IMS', function (): void {
    $mockService = $this->mock(ImsService::class);
    $mockService->shouldReceive('getGrades')
        ->once()
        ->with($this->courseCode, '1234567890', CivilIdTypeEnum::NATIONAL_CODE, Mockery::on(function (array $params): bool {
            return ($params['occurrence_id'] ?? null) === '42';
        }))
        ->andReturn(['data' => []]);

    getJson("/api/v1/shop/teacher/courses/{$this->courseCode}/grades?occurrence_id=42")
        ->assertOk();
});

// ─── Store (POST single) ────────────────────────────────────────────────────

it('store creates a single grade via IMS', function (): void {
    $mockService = $this->mock(ImsService::class);
    $mockService->shouldReceive('storeGrade')
        ->once()
        ->with($this->courseCode, '1234567890', CivilIdTypeEnum::NATIONAL_CODE, Mockery::type('array'))
        ->andReturn(['message' => 'Grade stored']);

    postJson("/api/v1/shop/teacher/courses/{$this->courseCode}/grades", [
        'enrolment_id' => 1,
        'grades'       => ['math' => 88.5],
    ])
        ->assertOk()
        ->assertJsonPath('message', 'Grade stored');
});

it('store validates payload', function (): void {
    postJson("/api/v1/shop/teacher/courses/{$this->courseCode}/grades", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['enrolment_id', 'grades']);
});

// ─── Store Bulk (POST bulk) ─────────────────────────────────────────────────

it('storeBulk creates bulk grades via IMS', function (): void {
    $mockService = $this->mock(ImsService::class);
    $mockService->shouldReceive('storeBulkGrades')
        ->once()
        ->with($this->courseCode, '1234567890', CivilIdTypeEnum::NATIONAL_CODE, Mockery::type('array'))
        ->andReturn(['message' => 'Grades stored']);

    postJson("/api/v1/shop/teacher/courses/{$this->courseCode}/grades/bulk", [
        'enrolments' => [
            ['id' => 1, 'grades' => ['math' => 95.5]],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('data.message', 'Grades stored');
});

it('storeBulk strips underscore-prefixed grade keys', function (): void {
    $mockService = $this->mock(ImsService::class);
    $mockService->shouldReceive('storeBulkGrades')
        ->once()
        ->with($this->courseCode, '1234567890', CivilIdTypeEnum::NATIONAL_CODE, Mockery::on(function (array $payload): bool {
            $grades = $payload['enrolments'][0]['grades'];

            return ! isset($grades['_internal']) && isset($grades['math']);
        }))
        ->andReturn(['message' => 'Grades stored']);

    postJson("/api/v1/shop/teacher/courses/{$this->courseCode}/grades/bulk", [
        'enrolments' => [
            [
                'id'     => 1,
                'grades' => [
                    '_internal' => 'hidden-data',
                    'math'      => 95.5,
                ],
            ],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('data.message', 'Grades stored');
});

it('storeBulk returns 422 when IMS validation fails', function (): void {
    $mockService = $this->mock(ImsService::class);
    $mockService->shouldReceive('storeBulkGrades')
        ->once()
        ->andThrow(new App\Exceptions\Integrations\UnrecoverableProvisioningException(
            'Validation failed',
            422,
            null,
            ['validation_errors' => ['course_code' => ['Invalid']]],
        ));

    postJson("/api/v1/shop/teacher/courses/{$this->courseCode}/grades/bulk", [
        'enrolments' => [
            ['id' => 1, 'grades' => ['math' => 95.5]],
        ],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['course_code']);
});

it('storeBulk validates payload', function (): void {
    postJson("/api/v1/shop/teacher/courses/{$this->courseCode}/grades/bulk", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['enrolments']);
});

// ─── Authentication ─────────────────────────────────────────────────────────

it('requires authenticated user for grades', function (): void {
    $this->app->get('auth')->forgetGuards();

    getJson("/api/v1/shop/teacher/courses/{$this->courseCode}/grades")
        ->assertUnauthorized();
});

it('requires authentication for store-grade', function (): void {
    $this->app->get('auth')->forgetGuards();

    postJson("/api/v1/shop/teacher/courses/{$this->courseCode}/grades", [
        'enrolment_id' => 1,
        'grades'       => ['math' => 88.5],
    ])->assertUnauthorized();
});

it('requires authentication for storeBulk', function (): void {
    $this->app->get('auth')->forgetGuards();

    postJson("/api/v1/shop/teacher/courses/{$this->courseCode}/grades/bulk", [
        'enrolments' => [
            ['id' => 1, 'grades' => ['math' => 95.5]],
        ],
    ])->assertUnauthorized();
});
