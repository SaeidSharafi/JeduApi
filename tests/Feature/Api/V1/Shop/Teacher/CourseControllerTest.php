<?php

declare(strict_types=1);

use App\Enums\User\CivilIdTypeEnum;
use App\Models\ProductDeliveryOption;
use App\Models\Teacher;
use App\Models\User;
use App\Services\Integrations\ImsService;

use function Pest\Laravel\getJson;

uses(Tests\Support\Traits\AuthTestTrait::class);

beforeEach(function (): void {
    $this->user = User::factory()->create([
        'civil_id'      => '1234567890',
        'civil_id_type' => CivilIdTypeEnum::NATIONAL_CODE,
    ]);
    $this->teacher = Teacher::factory()->create([
        'user_id' => $this->user->id,
    ]);

    $this->customer($this->user);
});

// ─── Success ────────────────────────────────────────────────────────────────

it('returns teacher courses from IMS enriched with local product image', function (): void {
    $pdo = ProductDeliveryOption::factory()
        ->create(['details_json' => ['ims_course_code' => 'IMS-100']]);

    $mockService = $this->mock(ImsService::class);
    $mockService->shouldReceive('getTeacherCourses')
        ->once()
        ->with('1234567890', CivilIdTypeEnum::NATIONAL_CODE, Mockery::type('array'))
        ->andReturn([
            'data' => [
                [
                    'code'                   => 'IMS-100',
                    'name'                   => 'Advanced JavaScript',
                    'start_date'             => '2026-03-21',
                    'end_date'               => '2026-06-21',
                    'is_current'             => true,
                    'has_grade_enabled'      => true,
                    'has_attendance_enabled' => true,
                ],
                [
                    'code'                   => 'IMS-200',
                    'name'                   => 'PHP Basics',
                    'start_date'             => '2026-01-01',
                    'end_date'               => '2026-03-01',
                    'is_current'             => false,
                    'has_grade_enabled'      => false,
                    'has_attendance_enabled' => true,
                ],
            ],
        ]);

    getJson(route('api.v1.shop.teacher.courses.index'))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.code', 'IMS-100')
        ->assertJsonPath('data.1.code', 'IMS-200')
        ->assertJsonPath('data.0.name', 'Advanced JavaScript')
        ->assertJsonPath('data.1.name', 'PHP Basics')
        ->assertJsonPath('data.0.is_current', true)
        ->assertJsonPath('data.0.has_grades_enabled', true)
        ->assertJsonPath('data.1.is_current', false)
        ->assertJsonPath('data.1.has_attendance_enabled', true)
        ->assertJsonPath('data.0.product_delivery_option_uuid', $pdo->uuid);
});

it('returns empty list when IMS has no courses', function (): void {
    $mockService = $this->mock(ImsService::class);
    $mockService->shouldReceive('getTeacherCourses')
        ->once()
        ->andReturn(['data' => []]);

    getJson(route('api.v1.shop.teacher.courses.index'))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

// ─── 403 when user is not a teacher ─────────────────────────────────────────

it('returns 403 when authenticated user has no teacher profile', function (): void {
    $nonTeacher = User::factory()->create();
    $this->customer($nonTeacher);

    getJson(route('api.v1.shop.teacher.courses.index'))
        ->assertForbidden();
});

// ─── 401 when unauthenticated ───────────────────────────────────────────────

it('returns 401 when not authenticated', function (): void {
    $this->app->get('auth')->forgetGuards();

    getJson(route('api.v1.shop.teacher.courses.index'))
        ->assertUnauthorized();
});

// ─── Query parameter filters ─────────────────────────────────────────────────

it('filters courses by current period', function (): void {
    $mockService = $this->mock(ImsService::class);
    $mockService->shouldReceive('getTeacherCourses')
        ->once()
        ->with('1234567890', CivilIdTypeEnum::NATIONAL_CODE, Mockery::on(function (array $params): bool {
            return ($params['period'] ?? null) === 'current';
        }))
        ->andReturn(['data' => []]);

    getJson(route('api.v1.shop.teacher.courses.index', ['period' => 'current']))
        ->assertOk();
});

it('filters courses by past period', function (): void {
    $mockService = $this->mock(ImsService::class);
    $mockService->shouldReceive('getTeacherCourses')
        ->once()
        ->with('1234567890', CivilIdTypeEnum::NATIONAL_CODE, Mockery::on(function (array $params): bool {
            return ($params['period'] ?? null) === 'past';
        }))
        ->andReturn(['data' => []]);

    getJson(route('api.v1.shop.teacher.courses.index', ['period' => 'past']))
        ->assertOk();
});

it('forwards arbitrary query params to IMS', function (): void {
    $mockService = $this->mock(ImsService::class);
    $mockService->shouldReceive('getTeacherCourses')
        ->once()
        ->with('1234567890', CivilIdTypeEnum::NATIONAL_CODE, Mockery::on(function (array $params): bool {
            return ($params['status'] ?? null) === 'current';
        }))
        ->andReturn(['data' => []]);

    getJson(route('api.v1.shop.teacher.courses.index', ['status' => 'current']))
        ->assertOk();
});
