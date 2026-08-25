<?php

declare(strict_types=1);

use App\Data\Shop\Student\MoodleSsoUrlData;
use App\Enums\Product\DeliveryMethodEnum;
use App\Models\Enrollment;
use App\Models\ProductDeliveryOption;
use App\Models\Teacher;
use App\Models\User;
use App\Services\Integrations\MoodleService;

uses(Tests\Support\Traits\AuthTestTrait::class);

beforeEach(function (): void {
    $this->customer();
});

/*
|--------------------------------------------------------------------------
| Student Moodle SSO Tests
|--------------------------------------------------------------------------
*/
describe('Student Moodle SSO', function (): void {
    it('returns 404 for non-owner enrollment', function (): void {
        $otherUser  = User::factory()->create();
        $enrollment = createEnrollment($otherUser, DeliveryMethodEnum::LMS_MOODLE);

        $this->postJson(route('api.v1.shop.student.courses.moodle.sso', ['enrollment' => $enrollment->uuid]))
            ->assertNotFound()
            ->assertJsonFragment(['message' => __('messages.enrollments.not_found')]);
    });

    it('returns 422 for non-moodle enrollment', function (): void {
        $enrollment = createEnrollment($this->user, DeliveryMethodEnum::IN_PERSON);

        $this->postJson(route('api.v1.shop.student.courses.moodle.sso', ['enrollment' => $enrollment->uuid]))
            ->assertUnprocessable()
            ->assertJsonFragment(['message' => __('messages.enrollments.not_moodle')]);
    });

    it('returns 422 when moodle provisioning is incomplete', function (): void {
        $enrollment                    = createEnrollment($this->user, DeliveryMethodEnum::LMS_MOODLE);
        $enrollment->provisioning_data = [];
        $enrollment->save();

        $this->postJson(route('api.v1.shop.student.courses.moodle.sso', ['enrollment' => $enrollment->uuid]))
            ->assertUnprocessable()
            ->assertJsonFragment(['message' => __('messages.enrollments.moodle_provisioning_incomplete')]);
    });

    it('returns sso url with default course wantsurl for valid moodle enrollment', function (): void {
        $pdo = ProductDeliveryOption::factory()->create([
            'delivery_method' => DeliveryMethodEnum::LMS_MOODLE,
            'details_json'    => ['moodle_course_id' => 101],
        ]);

        $enrollment = Enrollment::factory()->create([
            'customer_id'                => $this->user->id,
            'product_delivery_option_id' => $pdo->id,
            'provisioning_data'          => [
                'providers' => [
                    'moodle' => [
                        'status' => 'success',
                        'data'   => ['moodle_user_name' => 'testuser'],
                    ],
                ],
            ],
        ]);

        $ssoUrl  = 'https://moodle.test/auth/userkey/login.php?key=abc123';
        $ssoData = new MoodleSsoUrlData(url: $ssoUrl, wantsurl: '/course/view.php?id=101');

        $this->mock(MoodleService::class, function ($mock) use ($ssoData): void {
            $mock->shouldReceive('generateSsoUrl')
                ->once()
                ->with('testuser', '/course/view.php?id=101')
                ->andReturn($ssoData);
        });

        $this->postJson(route('api.v1.shop.student.courses.moodle.sso', ['enrollment' => $enrollment->uuid]))
            ->assertOk()
            ->assertJsonPath('data.url', $ssoUrl)
            ->assertJsonPath('data.wantsurl', '/course/view.php?id=101');
    });

    it('includes custom wantsurl when explicitly provided', function (): void {
        $enrollment                    = createEnrollment($this->user, DeliveryMethodEnum::LMS_MOODLE);
        $enrollment->provisioning_data = [
            'providers' => [
                'moodle' => [
                    'status' => 'success',
                    'data'   => ['moodle_user_name' => 'testuser'],
                ],
            ],
        ];
        $enrollment->save();

        $ssoUrl  = 'https://moodle.test/auth/userkey/login.php?key=abc123';
        $wants   = 'https://moodle.test/course/view.php?id=5';
        $ssoData = new MoodleSsoUrlData(url: $ssoUrl, wantsurl: $wants);

        $this->mock(MoodleService::class, function ($mock) use ($wants, $ssoData): void {
            $mock->shouldReceive('generateSsoUrl')
                ->once()
                ->with('testuser', $wants)
                ->andReturn($ssoData);
        });

        $this->postJson(route('api.v1.shop.student.courses.moodle.sso', [
            'enrollment' => $enrollment->uuid,
            'wantsurl'   => $wants,
        ]))
            ->assertOk()
            ->assertJsonPath('data.url', $ssoUrl)
            ->assertJsonPath('data.wantsurl', $wants);
    });

    it('uses moodle_username provisioning key fallback', function (): void {
        $enrollment = createEnrollment($this->user, DeliveryMethodEnum::LMS_MOODLE);
        $enrollment->provisioning_data = [
            'providers' => [
                'moodle' => [
                    'status' => 'success',
                    'data'   => ['moodle_username' => 'fallback_user'],
                ],
            ],
        ];
        $enrollment->save();

        $courseId    = data_get($enrollment->productDeliveryOption->details_json, 'moodle_course_id');
        $expectedUrl = $courseId ? "/course/view.php?id={$courseId}" : null;

        $ssoUrl  = 'https://moodle.test/auth/userkey/login.php?key=fallback123';
        $ssoData = new MoodleSsoUrlData(url: $ssoUrl, wantsurl: $expectedUrl);

        $this->mock(MoodleService::class, function ($mock) use ($expectedUrl, $ssoData): void {
            $mock->shouldReceive('generateSsoUrl')
                ->once()
                ->with('fallback_user', $expectedUrl)
                ->andReturn($ssoData);
        });

        $this->postJson(route('api.v1.shop.student.courses.moodle.sso', ['enrollment' => $enrollment->uuid]))
            ->assertOk()
            ->assertJsonPath('data.url', $ssoUrl);
    });

    it('returns 422 when MoodleService fails to generate sso url', function (): void {
        $enrollment                    = createEnrollment($this->user, DeliveryMethodEnum::LMS_MOODLE);
        $enrollment->provisioning_data = [
            'providers' => [
                'moodle' => [
                    'status' => 'success',
                    'data'   => ['moodle_user_name' => 'testuser'],
                ],
            ],
        ];
        $enrollment->save();

        $this->mock(MoodleService::class, function ($mock): void {
            $mock->shouldReceive('generateSsoUrl')
                ->once()
                ->with('testuser', \Mockery::any())
                ->andReturnNull();
        });

        $this->postJson(route('api.v1.shop.student.courses.moodle.sso', ['enrollment' => $enrollment->uuid]))
            ->assertUnprocessable()
            ->assertJsonFragment(['message' => __('messages.enrollments.moodle_service_error')]);
    });
});

/*
|--------------------------------------------------------------------------
| Teacher Moodle SSO Tests
|--------------------------------------------------------------------------
*/
describe('Teacher Moodle SSO', function (): void {
    it('returns 403 for non-teachers', function (): void {
        $otherUser = User::factory()->create();
        $teacher   = Teacher::factory()->create(['user_id' => $otherUser->id]);

        $pdo = ProductDeliveryOption::factory()->create([
            'delivery_method' => DeliveryMethodEnum::LMS_MOODLE,
        ]);
        $pdo->teachers()->attach($teacher);

        $this->postJson(route('api.v1.shop.teacher.courses.moodle.sso', ['deliveryOption' => $pdo->uuid]))
            ->assertForbidden();
    });

    it('returns 403 when teacher does not own the delivery option', function (): void {
        Teacher::factory()->create(['user_id' => $this->user->id]);

        $otherUser = User::factory()->create();
        $teacher   = Teacher::factory()->create(['user_id' => $otherUser->id]);

        $pdo = ProductDeliveryOption::factory()->create([
            'delivery_method' => DeliveryMethodEnum::LMS_MOODLE,
        ]);
        $pdo->teachers()->attach($teacher);

        $this->postJson(route('api.v1.shop.teacher.courses.moodle.sso', ['deliveryOption' => $pdo->uuid]))
            ->assertForbidden();
    });

    it('returns 422 for non-moodle delivery option', function (): void {
        $teacher = Teacher::factory()->create(['user_id' => $this->user->id]);

        $pdo = ProductDeliveryOption::factory()->create([
            'delivery_method' => DeliveryMethodEnum::IN_PERSON,
        ]);
        $pdo->teachers()->attach($teacher);

        $this->postJson(route('api.v1.shop.teacher.courses.moodle.sso', ['deliveryOption' => $pdo->uuid]))
            ->assertUnprocessable()
            ->assertJsonFragment(['message' => __('messages.enrollments.not_moodle')]);
    });

    it('returns 422 when teacher civil_id is empty', function (): void {
        $this->user->forceFill(['civil_id' => null])->save();

        $this->customer($this->user->fresh());

        $teacher = Teacher::factory()->create(['user_id' => $this->user->id]);


        $pdo = ProductDeliveryOption::factory()->create([
            'delivery_method' => DeliveryMethodEnum::LMS_MOODLE,
        ]);
        $pdo->teachers()->attach($teacher);

        $this->mock(MoodleService::class, function ($mock): void {
            $mock->shouldNotReceive('generateSsoUrl');
        });

        $this->postJson(route('api.v1.shop.teacher.courses.moodle.sso', ['deliveryOption' => $pdo->uuid]))
            ->assertUnprocessable()
            ->assertJsonFragment(['message' => __('messages.enrollments.moodle_provisioning_incomplete')]);
    });

    it('returns sso url with default course wantsurl for course owned by teacher', function (): void {
        $teacher = Teacher::factory()->create(['user_id' => $this->user->id]);

        $pdo = ProductDeliveryOption::factory()->create([
            'delivery_method' => DeliveryMethodEnum::LMS_MOODLE,
            'details_json'    => ['moodle_course_id' => 205],
        ]);
        $pdo->teachers()->attach($teacher);

        $ssoUrl  = 'https://moodle.test/auth/userkey/login.php?key=abc123';
        $ssoData = new MoodleSsoUrlData(url: $ssoUrl, wantsurl: '/course/view.php?id=205');

        $this->mock(MoodleService::class, function ($mock) use ($ssoData): void {
            $mock->shouldReceive('generateSsoUrl')
                ->once()
                ->with($this->user->civil_id, '/course/view.php?id=205')
                ->andReturn($ssoData);
        });

        $this->postJson(route('api.v1.shop.teacher.courses.moodle.sso', ['deliveryOption' => $pdo->uuid]))
            ->assertOk()
            ->assertJsonPath('data.url', $ssoUrl)
            ->assertJsonPath('data.wantsurl', '/course/view.php?id=205');
    });

    it('includes wantsurl in request when explicitly provided', function (): void {
        $teacher = Teacher::factory()->create(['user_id' => $this->user->id]);

        $pdo = ProductDeliveryOption::factory()->create([
            'delivery_method' => DeliveryMethodEnum::LMS_MOODLE,
        ]);
        $pdo->teachers()->attach($teacher);

        $ssoUrl  = 'https://moodle.test/auth/userkey/login.php?key=abc123';
        $wants   = 'https://moodle.test/course/view.php?id=5';
        $ssoData = new MoodleSsoUrlData(url: $ssoUrl, wantsurl: $wants);

        $this->mock(MoodleService::class, function ($mock) use ($wants, $ssoData): void {
            $mock->shouldReceive('generateSsoUrl')
                ->once()
                ->with($this->user->civil_id, $wants)
                ->andReturn($ssoData);
        });

        $this->postJson(route('api.v1.shop.teacher.courses.moodle.sso', [
            'deliveryOption' => $pdo->uuid,
            'wantsurl'       => $wants,
        ]))
            ->assertOk()
            ->assertJsonPath('data.url', $ssoUrl)
            ->assertJsonPath('data.wantsurl', $wants);
    });

    it('returns 422 when MoodleService fails to generate sso url', function (): void {
        $teacher = Teacher::factory()->create(['user_id' => $this->user->id]);

        $pdo = ProductDeliveryOption::factory()->create([
            'delivery_method' => DeliveryMethodEnum::LMS_MOODLE,
        ]);
        $pdo->teachers()->attach($teacher);

        $this->mock(MoodleService::class, function ($mock): void {
            $mock->shouldReceive('generateSsoUrl')
                ->once()
                ->with($this->user->civil_id, \Mockery::any())
                ->andReturnNull();
        });

        $this->postJson(route('api.v1.shop.teacher.courses.moodle.sso', ['deliveryOption' => $pdo->uuid]))
            ->assertUnprocessable()
            ->assertJsonFragment(['message' => __('messages.enrollments.moodle_service_error')]);
    });
});
