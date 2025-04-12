<?php

namespace Tests\Feature\Api\V1\Admin\Auth;

use App\Models\Admin;
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

test('admin can initiate authentication with email', function () {
    $admin = Admin::factory()->create([
        'email' => 'admin@example.com',
        'password' => null
    ]);

    $response = $this->postJson('/api/v1/admin/auth/initiate', [
        'identifier' => 'admin@example.com',
        'type' => 'email'
    ]);

    $response->assertOk()
        ->assertJson([
            'action' => 'OTP_LOGIN',
            'message' => 'Please request OTP to login.'
        ]);
});

test('non existent admin gets error', function () {
    $response = $this->postJson('/api/v1/admin/auth/initiate', [
        'identifier' => 'nonexistent@example.com',
        'type' => 'email'
    ]);

    $response->assertJson([
        'action' => 'NOT_FOUND',
        'message' => 'Admin account not found.'
    ]);
});

test('admin can request otp', function () {
    $admin = Admin::factory()->create(['email' => 'admin@example.com']);

    $response = $this->postJson('/api/v1/admin/auth/otp/request', [
        'identifier' => 'admin@example.com',
        'type' => 'email',
        'purpose' => 'LOGIN'
    ]);

    $response->assertOk()
        ->assertJson(['message' => 'OTP has been sent.']);

    Notification::assertSentTo($admin, OtpEmailNotification::class);
});

test('admin can verify otp and login', function () {
    $otp = '123456';
    $admin = Admin::factory()->withOtp($otp)->create([
        'email' => 'admin@example.com'
    ]);

    $response = $this->postJson('/api/v1/admin/auth/otp/verify', [
        'identifier' => 'admin@example.com',
        'type' => 'email',
        'otp' => $otp,
        'purpose' => 'LOGIN'
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'access_token',
            'token_type',
            'admin'
        ]);
});

test('admin can login with password', function () {
    $admin = Admin::factory()->create([
        'email' => 'admin@example.com',
        'password' => Hash::make('password123')
    ]);

    $response = $this->postJson('/api/v1/admin/auth/login/password', [
        'identifier' => 'admin@example.com',
        'type' => 'email',
        'password' => 'password123'
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'access_token',
            'token_type',
            'admin'
        ]);
});

test('admin can request password reset', function () {
    $admin = Admin::factory()->create(['email' => 'admin@example.com']);

    $response = $this->postJson('/api/v1/admin/auth/otp/request', [
        'identifier' => 'admin@example.com',
        'type' => 'email',
        'purpose' => 'PASSWORD_RESET'
    ]);

    $response->assertOk();
    Notification::assertSentTo($admin, OtpEmailNotification::class);
});

test('admin can verify otp for password reset', function () {
    $otp = '123456';
    $admin = Admin::factory()->withOtp($otp)->create([
        'email' => 'admin@example.com'
    ]);

    $response = $this->postJson('/api/v1/admin/auth/otp/verify', [
        'identifier' => 'admin@example.com',
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

test('admin can reset password with valid reset token', function () {
    $admin = Admin::factory()->create(['email' => 'admin@example.com']);
    $token = Password::broker('admins')->createToken($admin);

    $response = $this->postJson('/api/v1/admin/auth/password/reset/otp', [
        'identifier' => 'admin@example.com',
        'type' => 'email',
        'token' => $token,
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123'
    ]);

    $response->assertOk()
        ->assertJson(['message' => 'Password has been reset successfully.']);

    expect(Hash::check('newpassword123', $admin->fresh()->password))->toBeTrue();
});

test('admin can logout', function () {
    $admin = Admin::factory()->create();
    $token = $admin->createToken('admin_token')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/admin/auth/logout');

    $response->assertOk()
        ->assertJson(['message' => 'Successfully logged out']);

    $this->assertDatabaseCount('personal_access_tokens', 0);
});
