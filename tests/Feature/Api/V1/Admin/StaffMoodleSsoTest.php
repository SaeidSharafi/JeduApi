<?php

declare(strict_types=1);

use App\Data\Shop\Student\MoodleSsoUrlData;
use App\Models\Staff;
use App\Services\Integrations\MoodleService;

uses(Tests\Support\Traits\AuthTestTrait::class);

describe('Admin / Staff Moodle SSO', function (): void {
    it('returns 401 for unauthenticated request', function (): void {
        $this->postJson(route('api.v1.admin.moodle.sso'))
            ->assertUnauthorized();
    });

    it('returns sso url for authenticated staff member using email', function (): void {
        $staff = Staff::factory()->create([
            'email' => 'admin@school.test',
        ]);

        $this->admin_user($staff);

        $ssoUrl  = 'https://moodle.test/auth/userkey/login.php?key=staff123';
        $ssoData = new MoodleSsoUrlData(url: $ssoUrl, wantsurl: null);

        $this->mock(MoodleService::class, function ($mock) use ($staff, $ssoData): void {
            $mock->shouldReceive('generateSsoUrl')
                ->once()
                ->with($staff->email, Mockery::any())
                ->andReturn($ssoData);
        });

        $this->postJson(route('api.v1.admin.moodle.sso'))
            ->assertOk()
            ->assertJsonPath('data.url', $ssoUrl)
            ->assertJsonPath('data.wantsurl', null);
    });

    it('includes wantsurl when explicitly provided for staff', function (): void {
        $staff = Staff::factory()->create([
            'email' => 'admin@school.test',
        ]);

        $this->admin_user($staff);

        $ssoUrl  = 'https://moodle.test/auth/userkey/login.php?key=staff123';
        $wants   = 'https://moodle.test/admin/settings.php';
        $ssoData = new MoodleSsoUrlData(url: $ssoUrl, wantsurl: $wants);

        $this->mock(MoodleService::class, function ($mock) use ($staff, $wants, $ssoData): void {
            $mock->shouldReceive('generateSsoUrl')
                ->once()
                ->with($staff->email, $wants)
                ->andReturn($ssoData);
        });

        $this->postJson(route('api.v1.admin.moodle.sso', ['wantsurl' => $wants]))
            ->assertOk()
            ->assertJsonPath('data.url', $ssoUrl)
            ->assertJsonPath('data.wantsurl', $wants);
    });

    it('returns 422 when MoodleService fails to generate sso url', function (): void {
        $staff = Staff::factory()->create([
            'email' => 'admin@school.test',
        ]);

        $this->admin_user($staff);

        $this->mock(MoodleService::class, function ($mock) use ($staff): void {
            $mock->shouldReceive('generateSsoUrl')
                ->once()
                ->with($staff->email, Mockery::any())
                ->andReturnNull();
        });

        $this->postJson(route('api.v1.admin.moodle.sso'))
            ->assertUnprocessable()
            ->assertJsonFragment(['message' => __('messages.enrollments.moodle_service_error')]);
    });
});
