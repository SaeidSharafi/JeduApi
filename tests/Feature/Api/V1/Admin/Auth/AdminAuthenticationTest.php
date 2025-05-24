<?php

declare(strict_types=1);

use App\Dto\OtpManager\OtpDto;
use App\Enums\OtpType;
use App\Models\Admin;
use App\Notifications\Auth\OtpEmailNotification;
use App\Notifications\Auth\OtpSmsNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Notification::fake();
    $minOtpCode         = config('otp.code_min');
    $maxOtpCode         = config('otp.code_max');
    $this->otpCode      = random_int($minOtpCode, $maxOtpCode);
    $this->trackingCode = 'test-tracking';

    $fakeGenerator = $this->app->make(App\Contracts\OtpGeneratorInterface::class);
    if ($fakeGenerator instanceof Tests\Fakes\FakeOtpGenerator) {
        $fakeGenerator->setNextOtpCode($this->otpCode)
            ->setNextTrackingCode($this->trackingCode);
    }
});

test('admin can initiate authentication with email', function (): void {
    $admin = Admin::factory()->create([
        'email'    => 'admin@example.com',
        'password' => null,
    ]);

    $response = $this->postJson('/api/v1/admin/auth/initiate', [
        'identifier' => 'admin@example.com',
        'type'       => 'email',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => [
                'tracking_code',
                'otp_type',
                'identifier',
                'login_method',
            ],
        ])
        ->assertJson([
            'data' => [
                'login_method' => 'OTP',
            ],
        ]);
});

test('admin can initiate authentication with phone', function (): void {
    $admin = Admin::factory()->create([
        'phone'    => '09351236547',
        'password' => null,
    ]);

    $response = $this->postJson(route('api.v1.admin.auth.initiate'), [
        'identifier' => '09351236547',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => [
                'tracking_code',
                'otp_type',
                'identifier',
                'login_method',
            ],
        ])
        ->assertJson([
            'data' => [
                'login_method' => 'OTP',
            ],
        ]);
});

test('non existent admin gets error', function (): void {
    $response = $this->postJson(\route('api.v1.admin.auth.initiate'), [
        'identifier' => 'nonexistent@example.com',
    ]);

    $response->assertNotFound()
        ->assertJson([
            'message' => 'User not found',
        ]);
});

test('admin can reset otp', function (): void {
    $admin = Admin::factory()->create(['email' => 'admin@example.com']);

    $response = $this->postJson(route('api.v1.admin.auth.otp-resend'), [
        'identifier' => 'admin@example.com',
        'otp_type'   => OtpType::SIGNIN->value,
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => [
                'tracking_code',
                'otp_type',
                'identifier',
                'login_method',
            ],
        ]);

    Notification::assertSentTo($admin, OtpEmailNotification::class);
});

test('admin can resend otp with phone', function (): void {
    $admin = Admin::factory()->create(['phone' => '09326542145']);

    $response = $this->postJson(\route('api.v1.admin.auth.otp-resend'), [
        'identifier' => '09326542145',
        'otp_type'   => OtpType::SIGNIN->value,
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => [
                'tracking_code',
                'otp_type',
                'identifier',
                'login_method',
            ],
        ]);

    Notification::assertSentTo($admin, OtpSmsNotification::class);
});
test('admin with password gets password login action', function (): void {
    $user = Admin::factory()->create([
        'email'    => 'test@example.com',
        'password' => Hash::make('password123'),
    ]);

    $response = $this->postJson(route('api.v1.admin.auth.initiate'), [
        'identifier' => 'test@example.com',
    ]);

    $response->assertOk()
        ->assertJson([
            'message' => 'User has set password',
            'data'    => [
                'login_method' => 'PASSWORD',
            ],
            'metadata' => [],
        ]);
});
test('admin can verify otp and login', function (): void {
    Admin::factory()->create(['email' => 'admin@example.com']);
    $this->postJson(\route('api.v1.admin.auth.initiate'), [
        'identifier' => 'admin@example.com',
    ]);

    $response = $this->postJson(\route('api.v1.admin.auth.otp-verify'), [
        'identifier'    => 'admin@example.com',
        'otp_code'      => $this->otpCode,
        'tracking_code' => $this->trackingCode,
        'otp_type'      => OtpType::SIGNIN->value,
    ]);

    $response
        ->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => [
                'token',
                'expires_at',
                'type',
                'user',
            ],
        ]);
});

test('admin can verify otp and login with phone', function (): void {
    $admin        = Admin::factory()->create(['phone' => '09301234567']);
    $trackingCode = 'test-tracking';

    Cache::put('otp_09301234567_admin_value_SIGNIN', new OtpDto($this->otpCode, $trackingCode), 300);

    $response = $this->postJson('/api/v1/admin/auth/otp/verify', [
        'identifier'    => '09301234567',
        'type'          => 'phone',
        'otp_code'      => $this->otpCode,
        'tracking_code' => $trackingCode,
        'otp_type'      => OtpType::SIGNIN->value,
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => [
                'token',
                'expires_at',
                'type',
                'user',
            ],
        ]);
});

test('admin can login with password', function (): void {
    $admin = Admin::factory()->create([
        'email'    => 'admin@example.com',
        'password' => Hash::make('password123'),
    ]);

    $response = $this->postJson('/api/v1/admin/auth/login/password', [
        'identifier' => 'admin@example.com',
        'type'       => 'email',
        'password'   => 'password123',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => [
                'token',
                'expires_at',
                'type',
                'user',
            ],
        ]);
});

test('admin can login with phone and password', function (): void {
    $admin = Admin::factory()->create([
        'phone'    => '09301234567',
        'password' => Hash::make('password123'),
    ]);

    $response = $this->postJson('/api/v1/admin/auth/login/password', [
        'identifier' => '09301234567',
        'type'       => 'phone',
        'password'   => 'password123',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => [
                'token',
                'expires_at',
                'type',
                'user',
            ],
        ]);
});
test('non existent admin can not verify otp', function (): void {

    $response = $this->postJson(route('api.v1.admin.auth.otp-verify'), [
        'identifier'    => 'test@example.com',
        'type'          => 'email',
        'otp_code'      => $this->otpCode,
        'tracking_code' => $this->trackingCode,
        'otp_type'      => OtpType::SIGNIN->value,
    ]);

    $response->assertNotFound();
});
test('admin can logout', function (): void {
    $admin = Admin::factory()->create();
    $token = $admin->createToken('admin_token')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/admin/auth/logout');

    $response->assertStatus(204);

    $this->assertDatabaseCount('personal_access_tokens', 0);
});
