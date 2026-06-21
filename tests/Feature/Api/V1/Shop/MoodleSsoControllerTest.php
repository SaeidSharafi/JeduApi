<?php

declare(strict_types=1);

use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\System\SettingKeyEnum;
use App\Models\Setting;
use App\Services\Integrations\MoodleService;

uses(Tests\Support\Traits\AuthTestTrait::class);

beforeEach(function (): void {
    $this->customer();
});

it('returns 404 for non-owner enrollment', function (): void {
    $otherUser  = App\Models\User::factory()->create();
    $enrollment = createEnrollment($otherUser, DeliveryMethodEnum::LMS_MOODLE);

    $this->postJson(route('api.v1.shop.my-courses.moodle.sso', ['enrollment' => $enrollment->uuid]))
        ->assertNotFound()
        ->assertJsonFragment(['message' => __('messages.enrollments.not_found')]);
});

it('returns 422 for non-moodle enrollment', function (): void {
    $enrollment = createEnrollment($this->user, DeliveryMethodEnum::IN_PERSON);

    $this->postJson(route('api.v1.shop.my-courses.moodle.sso', ['enrollment' => $enrollment->uuid]))
        ->assertUnprocessable()
        ->assertJsonFragment(['message' => __('messages.enrollments.not_moodle')]);
});

it('returns 422 when moodle provisioning is incomplete', function (): void {
    $enrollment = createEnrollment($this->user, DeliveryMethodEnum::LMS_MOODLE);
    // provisioning_data has no moodle_user_name
    $enrollment->provisioning_data = [];
    $enrollment->save();

    $this->postJson(route('api.v1.shop.my-courses.moodle.sso', ['enrollment' => $enrollment->uuid]))
        ->assertUnprocessable()
        ->assertJsonFragment(['message' => __('messages.enrollments.moodle_provisioning_incomplete')]);
});

it('returns sso url for valid moodle enrollment', function (): void {
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

    Setting::query()->updateOrCreate(
        ['key' => SettingKeyEnum::MOODLE->value],
        [
            'value' => [
                'enabled'            => true,
                'base_url'           => 'https://moodle.test',
                'token'              => 'admin-token',
                'auth_userkey_token' => 'userkey-token',
            ],
        ]
    );

    $ssoUrl = 'https://moodle.test/auth/userkey/login.php?key=abc123';

    $this->mock(MoodleService::class, function ($mock) use ($ssoUrl): void {
        $mock->shouldReceive('createUserKey')
            ->once()
            ->with('testuser')
            ->andReturn($ssoUrl);
    });

    $this->postJson(route('api.v1.shop.my-courses.moodle.sso', ['enrollment' => $enrollment->uuid]))
        ->assertOk()
        ->assertJsonPath('data.url', $ssoUrl);
});

it('includes wantsurl in request to MoodleService when provided', function (): void {
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

    Setting::query()->updateOrCreate(
        ['key' => SettingKeyEnum::MOODLE->value],
        [
            'value' => [
                'enabled'            => true,
                'base_url'           => 'https://moodle.test',
                'token'              => 'admin-token',
                'auth_userkey_token' => 'userkey-token',
            ],
        ]
    );

    $ssoUrl = 'https://moodle.test/auth/userkey/login.php?key=abc123';
    $wants  = 'https://moodle.test/course/view.php?id=5';

    $this->mock(MoodleService::class, function ($mock) use ($ssoUrl): void {
        $mock->shouldReceive('createUserKey')
            ->once()
            ->with('testuser')
            ->andReturn($ssoUrl);
    });

    $this->postJson(route('api.v1.shop.my-courses.moodle.sso', [
        'enrollment' => $enrollment->uuid,
        'wantsurl'   => $wants,
    ]))
        ->assertOk()
        ->assertJsonPath('data.url', $ssoUrl)
        ->assertJsonPath('data.wantsurl', $wants);
});

it('uses moodle_username provisioning key fallback', function (): void {
    $enrollment = createEnrollment($this->user, DeliveryMethodEnum::LMS_MOODLE);
    // Use alternative key 'moodle_username' instead of 'moodle_user_name'
    $enrollment->provisioning_data = [
        'providers' => [
            'moodle' => [
                'status' => 'success',
                'data'   => ['moodle_username' => 'fallback_user'],
            ],
        ],
    ];
    $enrollment->save();

    Setting::query()->updateOrCreate(
        ['key' => SettingKeyEnum::MOODLE->value],
        [
            'value' => [
                'enabled'            => true,
                'base_url'           => 'https://moodle.test',
                'token'              => 'admin-token',
                'auth_userkey_token' => 'userkey-token',
            ],
        ]
    );

    $ssoUrl = 'https://moodle.test/auth/userkey/login.php?key=fallback123';

    $this->mock(MoodleService::class, function ($mock) use ($ssoUrl): void {
        $mock->shouldReceive('createUserKey')
            ->once()
            ->with('fallback_user')
            ->andReturn($ssoUrl);
    });

    $this->postJson(route('api.v1.shop.my-courses.moodle.sso', ['enrollment' => $enrollment->uuid]))
        ->assertOk()
        ->assertJsonPath('data.url', $ssoUrl);
});

it('returns 422 when MoodleService throws an exception', function (): void {
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

    Setting::query()->updateOrCreate(
        ['key' => SettingKeyEnum::MOODLE->value],
        [
            'value' => [
                'enabled'            => true,
                'base_url'           => 'https://moodle.test',
                'token'              => 'admin-token',
                'auth_userkey_token' => 'userkey-token',
            ],
        ]
    );

    $this->mock(MoodleService::class, function ($mock): void {
        $mock->shouldReceive('createUserKey')
            ->once()
            ->andThrow(new RuntimeException('Moodle API error'));
    });

    $this->postJson(route('api.v1.shop.my-courses.moodle.sso', ['enrollment' => $enrollment->uuid]))
        ->assertUnprocessable()
        ->assertJsonFragment(['message' => __('messages.enrollments.moodle_service_error')]);
});
