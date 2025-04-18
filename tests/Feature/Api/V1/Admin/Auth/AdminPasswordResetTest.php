<?php

use App\Enums\OtpType;
use App\Models\User;
use App\Models\Admin;
use App\Notifications\Auth\OtpEmailNotification;
use App\Services\OtpManagerService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();

    // Setup test OTP data
    $minOtpCode = config('otp.code_min');
    $maxOtpCode = config('otp.code_max');
    $this->otpCode = random_int($minOtpCode, $maxOtpCode);
    $this->invalidOtpCode = $this->otpCode + 1 > $maxOtpCode ? $this->otpCode - 1 : $this->otpCode + 1;
    $this->trackingCode = 'test-tracking';

    $waitingTime = 120;
    $otpManagerMock = $this->mock(OtpManagerService::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods()
        ->shouldReceive('generateTrackingCode')
        ->withNoArgs()
        ->andReturn($this->trackingCode)
        ->shouldReceive('generateCode')
        ->withNoArgs()
        ->andReturn($this->otpCode)
        ->getMock();
    $reflection = new \ReflectionClass($otpManagerMock);
    $property = $reflection->getProperty('waitingTime');
    $property->setValue($otpManagerMock, $waitingTime);
});
test('admin can request password reset otp', function () {
    $admin = Admin::factory()->create([
        'email'    => 'admin1@example.com',
        'phone'    => '09301234567',
        'password' => Hash::make('oldpassword'),
    ]);

    $response = $this->postJson(route('api.v1.admin.auth.forgot-password'), [
        'identifier' => 'admin1@example.com',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => [
                'tracking_code',
                'otp_type',
                'identifier',
                'login_method'
            ]
        ]);

    Notification::assertSentTo($admin, OtpEmailNotification::class);
});

test('admin without password cannot request password reset', function () {
    Admin::factory()->create([
        'email'    => 'admin2@example.com',
        'phone'    => '09301234567',
        'password' => null,
    ]);

    $response = $this->postJson(route('api.v1.admin.auth.forgot-password'), [
        'identifier' => 'admin2@example.com',
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'message' => 'User does not have password'
        ]);
});

test('non existent admin cannot request password reset', function () {
    $response = $this->postJson(route('api.v1.admin.auth.forgot-password'), [
        'identifier' => 'nonexistent@example.com',
    ]);

    $response->assertNotFound()
        ->assertJson([
            'message' => 'User not found'
        ]);
});
test('admin without password cannot reset password', function () {
    Admin::factory()->create([
        'email' => 'user2@example.com',
        'password' => null,
    ]);

    $response = $this->postJson(route('api.v1.admin.auth.password-reset'), [
        'identifier' => 'user2@example.com',
        'tracking_code' => $this->trackingCode,
        'otp_code' => $this->otpCode, // Doesn't matter, user won't be found first
        'password' => 'newpassword',
        'password_confirmation' => 'newpassword',
    ]);
    $response->assertStatus(422)
        ->assertJson([
            'message' => 'User does not have password'
        ]);
});
test('admin can reset password with valid otp', function () {
    $admin = Admin::factory()->create([
        'email'    => 'admin3@example.com',
        'phone'    => '09301234567',
        'password' => Hash::make('oldpassword'),
    ]);

    $forgotResponse = $this->postJson(route('api.v1.admin.auth.forgot-password'), [
        'identifier' => 'admin3@example.com',
    ]);

    $trackingCode = $this->trackingCode;

    // Then reset password with OTP
    $response = $this->postJson(route('api.v1.admin.auth.password-reset'), [
        'identifier'            => 'admin3@example.com',
        'tracking_code'         => $trackingCode,
        'otp_code'              => $this->otpCode,
        'password'              => 'newpassword',
        'password_confirmation' => 'newpassword',
    ]);

    $response
        ->assertOk()
        ->assertJson([
            "message"  => "Operation successful.",
            "data"     => "Password reset OTP sent successfully",
            "metadata" => []
        ]);

    // Verify password was actually changed
    $admin->refresh();
    expect(Hash::check('newpassword', $admin->password))->toBeTrue();
});

test('admin cannot reset password with invalid otp', function () {
    $admin = Admin::factory()->create([
        'email'    => 'admin4@example.com',
        'phone'    => '09301234567',
        'password' => Hash::make('oldpassword'),
    ]);

    // First request the OTP
    $forgotResponse = $this->postJson(route('api.v1.admin.auth.forgot-password'), [
        'identifier' => 'admin4@example.com',
    ]);

    $trackingCode = $this->trackingCode;

    // Try resetting with invalid OTP
    $response = $this->postJson(route('api.v1.admin.auth.password-reset'), [
        'identifier'            => 'admin4@example.com',
        'tracking_code'         => $trackingCode,
        'otp_code'              => $this->invalidOtpCode,
        'password'              => 'newpassword',
        'password_confirmation' => 'newpassword',
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'message' => 'Invalid OTP code'
        ]);

    // Verify password was not changed
    $admin->refresh();
    expect(Hash::check('oldpassword', $admin->password))->toBeTrue();
});
