<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use App\Notifications\OtpEmailNotification;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
});

test('user can initiate authentication with email', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => null
    ]);

    $response = $this->postJson('/api/v1/auth/initiate', [
        'identifier' => 'test@example.com',
        'type' => 'email'
    ]);

    $response->assertOk()
        ->assertJson([
            'action' => 'OTP_LOGIN',
            'message' => 'Please request OTP to login.'
        ]);
});

test('user can initiate authentication with phone', function () {
    $user = User::factory()->create([
        'phone' => '1234567890',
        'password' => null
    ]);

    $response = $this->postJson('/api/v1/auth/initiate', [
        'identifier' => '1234567890',
        'type' => 'phone'
    ]);

    $response->assertOk()
        ->assertJson([
            'action' => 'OTP_LOGIN',
            'message' => 'Please request OTP to login.'
        ]);
});

test('user with password gets password login action', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => Hash::make('password123')
    ]);

    $response = $this->postJson('/api/v1/auth/initiate', [
        'identifier' => 'test@example.com',
        'type' => 'email'
    ]);

    $response->assertOk()
        ->assertJson([
            'action' => 'PASSWORD_LOGIN',
            'message' => 'Please login with password.'
        ]);
});

test('non existent user gets register action', function () {
    $response = $this->postJson('/api/v1/auth/initiate', [
        'identifier' => 'nonexistent@example.com',
        'type' => 'email'
    ]);

    $response->assertOk()
        ->assertJson([
            'action' => 'REGISTER',
            'message' => 'User not found. Registration required.'
        ]);
});

test('user can request otp', function () {
    $user = User::factory()->create(['email' => 'test@example.com']);

    $response = $this->postJson('/api/v1/auth/otp/request', [
        'identifier' => 'test@example.com',
        'type' => 'email',
        'purpose' => 'LOGIN'
    ]);

    $response->assertOk()
        ->assertJson(['message' => 'OTP has been sent.']);

    Notification::assertSentTo($user, OtpEmailNotification::class);
});

test('user can verify otp and login', function () {
    $otp = '123456';
    $user = User::factory()->withOtp($otp)->create([
        'email' => 'test@example.com'
    ]);

    $response = $this->postJson('/api/v1/auth/otp/verify', [
        'identifier' => 'test@example.com',
        'type' => 'email',
        'otp' => $otp,
        'purpose' => 'LOGIN'
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'access_token',
            'token_type',
            'user'
        ]);
});

test('user can login with password', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => Hash::make('password123')
    ]);

    $response = $this->postJson('/api/v1/auth/login/password', [
        'identifier' => 'test@example.com',
        'type' => 'email',
        'password' => 'password123'
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'access_token',
            'token_type',
            'user'
        ]);
});

test('user can request password reset', function () {
    $user = User::factory()->create(['email' => 'test@example.com']);

    $response = $this->postJson('/api/v1/auth/otp/request', [
        'identifier' => 'test@example.com',
        'type' => 'email',
        'purpose' => 'PASSWORD_RESET'
    ]);

    $response->assertOk()
        ->assertJson(['message' => 'OTP has been sent.']);

    Notification::assertSentTo($user, OtpEmailNotification::class);
});

test('user can verify otp for password reset', function () {
    $otp = '123456';
    $user = User::factory()->withOtp($otp)->create([
        'email' => 'test@example.com'
    ]);

    $response = $this->postJson('/api/v1/auth/otp/verify', [
        'identifier' => 'test@example.com',
        'type' => 'email',
        'otp' => $otp,
        'purpose' => 'PASSWORD_RESET'
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'reset_token',
            'message'
        ]);
});

test('user can reset password with valid reset token', function () {
    $user = User::factory()->create(['email' => 'test@example.com']);
    $token = Password::createToken($user);

    $response = $this->postJson('/api/v1/auth/password/reset/otp', [
        'identifier' => 'test@example.com',
        'type' => 'email',
        'token' => $token,
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123'
    ]);

    $response->assertOk()
        ->assertJson(['message' => 'Password has been reset successfully.']);

    expect(Hash::check('newpassword123', $user->fresh()->password))->toBeTrue();
});

test('authenticated user can logout', function () {
    $user = User::factory()->create();
    $token = $user->createToken('auth_token')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/auth/logout');

    $response->assertOk()
        ->assertJson(['message' => 'Successfully logged out']);

    $this->assertDatabaseCount('personal_access_tokens', 0);
});
