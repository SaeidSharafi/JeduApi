<?php

namespace Tests\Feature\Api\V1\Admin\Auth;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('admin auth requires valid email format', function () {
    $response = $this->postJson('/api/v1/admin/auth/initiate', [
        'identifier' => 'not-an-email',
        'type' => 'email'
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['identifier']);
});

test('admin otp request requires valid purpose', function () {
    $admin = Admin::factory()->create();

    $response = $this->postJson('/api/v1/admin/auth/otp/request', [
        'identifier' => $admin->email,
        'type' => 'email',
        'purpose' => 'INVALID'
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['purpose']);
});

test('admin otp verification requires valid purpose', function () {
    $admin = Admin::factory()->withOtp('123456')->create();

    $response = $this->postJson('/api/v1/admin/auth/otp/verify', [
        'identifier' => $admin->email,
        'type' => 'email',
        'otp' => '123456',
        'purpose' => 'INVALID'
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['purpose']);
});

test('admin otp verification requires valid identifier', function () {
    $response = $this->postJson('/api/v1/admin/auth/otp/verify', [
        'identifier' => 'invalid-email',
        'type' => 'email',
        'otp' => '111111',
        'purpose' => 'LOGIN'
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['identifier']);
});

test('admin otp verification fails with wrong otp', function () {
    $admin = Admin::factory()->withOtp('123456')->create();

    $response = $this->postJson('/api/v1/admin/auth/otp/verify', [
        'identifier' => $admin->email,
        'type' => 'email',
        'otp' => '111111',
        'purpose' => 'LOGIN'
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['otp']);
});

test('admin otp verification fails with expired otp', function () {
    $admin = Admin::factory()->create();

    // Create expired OTP
    $admin->otp()->create([
        'identifier' => $admin->email,
        'type' => 'email',
        'code' => '123456',
        'expires_at' => now()->subMinutes(6)
    ]);

    $response = $this->postJson('/api/v1/admin/auth/otp/verify', [
        'identifier' => $admin->email,
        'type' => 'email',
        'otp' => '123456',
        'purpose' => 'LOGIN'
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['otp']);
});

test('admin password reset requires strong password', function () {
    $admin = Admin::factory()->create(['email' => 'admin@example.com']);
    $token = Password::broker('admins')->createToken($admin);

    $response = $this->postJson('/api/v1/admin/auth/password/reset/otp', [
        'identifier' => 'admin@example.com',
        'type' => 'email',
        'token' => $token,
        'password' => 'weak',
        'password_confirmation' => 'weak'
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

test('admin cannot use expired reset token', function () {
    $admin = Admin::factory()->create([
        'reset_token' => null // expired or used token
    ]);

    $response = $this->postJson('/api/v1/admin/auth/password/reset/otp', [
        'identifier' => $admin->email,
        'type' => 'email',
        'reset_token' => 'any-token',
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123'
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['reset_token']);
});

test('admin cannot use invalid reset token', function () {
    $admin = Admin::factory()->create(['email' => 'admin@example.com']);

    $response = $this->postJson('/api/v1/admin/auth/password/reset/otp', [
        'identifier' => 'admin@example.com',
        'type' => 'email',
        'token' => 'invalid-token',
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123'
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['token']);
});

test('admin auth with non-existent account returns proper error', function () {
    $response = $this->postJson('/api/v1/admin/auth/otp/request', [
        'identifier' => 'nonexistent@example.com',
        'type' => 'email',
        'purpose' => 'LOGIN'
    ]);

    $response->assertNotFound()
        ->assertJson([
            'status' => 'error',
            'message' => 'Admin account not found.'
        ]);
});

test('admin logout requires valid auth token', function () {
    $response = $this->postJson('/api/v1/admin/auth/logout');

    $response->assertStatus(401);
});

test('admin cannot use invalid auth token', function () {
    $response = $this->withHeader('Authorization', 'Bearer invalid-token')
        ->postJson('/api/v1/admin/auth/logout');

    $response->assertStatus(401);
});
