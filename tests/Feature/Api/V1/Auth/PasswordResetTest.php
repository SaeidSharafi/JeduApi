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

    $otpManagerMock =  $this->mock(OtpManagerService::class)
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

test('user can request password reset otp', function () {
    $user = User::factory()->create([
        'email' => 'user1@example.com',
        'password' => Hash::make('oldpassword'),
    ]);

    $response = $this->postJson(route('api.v1.auth.forgot-password'), [
        'identifier' => 'user1@example.com',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => [
                'tracking_code',
                'otp_type',
                'identifier'
            ]
        ]);

    Notification::assertSentTo($user, OtpEmailNotification::class);
});

test('user without password cannot request password reset', function () {
    User::factory()->create([
        'email' => 'user2@example.com',
        'password' => null,
    ]);

    $response = $this->postJson(route('api.v1.auth.forgot-password'), [
        'identifier' => 'user2@example.com',
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'message' => 'User does not have password'
        ]);
});

test('non existent user cannot request password reset', function () {
    $response = $this->postJson(route('api.v1.auth.forgot-password'), [
        'identifier' => 'nonexistent@example.com',
    ]);

    $response->assertNotFound()
        ->assertJson([
            'message' => 'User not found'
        ]);
});
test('non existent user cannot reset password', function () {
    // Act: Post to the password-reset endpoint with a non-existent user
    $response = $this->postJson(route('api.v1.auth.password-reset'), [
        'identifier' => 'nonexistent@example.com',
        'tracking_code' => $this->trackingCode,
        'otp_code' => $this->otpCode, // Doesn't matter, user won't be found first
        'password' => 'newpassword',
        'password_confirmation' => 'newpassword',
    ]);

    $response->assertNotFound()
        ->assertJson([
            'message' => 'User not found'
        ]);
});
test('user without password cannot reset password', function () {
    User::factory()->create([
        'email' => 'user2@example.com',
        'password' => null,
    ]);

    $response = $this->postJson(route('api.v1.auth.password-reset'), [
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

test('user can reset password with valid otp', function () {
    $user = User::factory()->create([
        'email' => 'user3@example.com',
        'phone' => '09301234567',
        'password' => Hash::make('oldpassword'),
    ]);

    $forgotResponse = $this->postJson(route('api.v1.auth.forgot-password'), [
        'identifier' => 'user3@example.com',
    ]);

    // Then reset password with OTP
    $response = $this->postJson(route('api.v1.auth.password-reset'), [
        'identifier' => 'user3@example.com',
        'tracking_code' => $this->trackingCode,
        'otp_code' => $this->otpCode,
        'password' => 'newpassword',
        'password_confirmation' => 'newpassword',
    ]);

    $response
        ->assertOk()
        ->assertJson([
            'message' => 'Password reset successfully'
        ]);

    // Verify password was actually changed
    $user->refresh();
    expect(Hash::check('newpassword', $user->password))->toBeTrue();
});

test('user cannot reset password with invalid otp', function () {
    $user = User::factory()->create([
        'email' => 'user4@example.com',
        'phone' => '09301234567',
        'password' => Hash::make('oldpassword'),
    ]);

    // First request the OTP
    $forgotResponse = $this->postJson(route('api.v1.auth.forgot-password'), [
        'identifier' => 'user4@example.com',
    ]);

    $trackingCode = $this->trackingCode;

    $response = $this->postJson(route('api.v1.auth.password-reset'), [
        'identifier' => 'user4@example.com',
        'tracking_code' => $trackingCode,
        'otp_code' => $this->invalidOtpCode,
        'password' => 'newpassword',
        'password_confirmation' => 'newpassword',
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'message' => 'Invalid OTP code'
        ]);

    // Verify password was not changed
    $user->refresh();
    expect(Hash::check('oldpassword', $user->password))->toBeTrue();
});

test('password reset requires password confirmation', function () {
    $user = User::factory()->create([
        'email' => 'user5@example.com',
        'phone' => '09301234567',
        'password' => Hash::make('oldpassword'),
    ]);

    $forgotResponse = $this->postJson(route('api.v1.auth.forgot-password'), [
        'identifier' => 'user5@example.com',
    ]);

    $trackingCode = $this->trackingCode;

    $response = $this->postJson(route('api.v1.auth.password-reset'), [
        'identifier' => 'user5@example.com',
        'tracking_code' => $trackingCode,
        'otp_code' => $this->otpCode,
        'password' => 'newpassword',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(
            ['password']
        );

    // Verify password was not changed
    $user->refresh();
    expect(Hash::check('oldpassword', $user->password))->toBeTrue();
});

test('passwords must match for reset', function () {
    $user = User::factory()->create([
        'email' => 'user6@example.com',
        'phone' => '09301234567',
        'password' => Hash::make('oldpassword'),
    ]);

    $forgotResponse = $this->postJson(route('api.v1.auth.forgot-password'), [
        'identifier' => 'user6@example.com',
    ]);

    $trackingCode = $this->trackingCode;

    $response = $this->postJson(route('api.v1.auth.password-reset'), [
        'identifier' => 'user6@example.com',
        'tracking_code' => $trackingCode,
        'otp_code' => $this->otpCode,
        'password' => 'newpassword',
        'password_confirmation' => 'differentpassword',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);

    // Verify password was not changed
    $user->refresh();
    expect(Hash::check('oldpassword', $user->password))->toBeTrue();
});
