<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('initiate auth requires valid email format', function () {
    $response = $this->postJson('/api/v1/auth/initiate', [
        'identifier' => 'not-an-email',
        'type' => 'email'
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['identifier']);
});

test('initiate auth requires valid type', function () {
    $response = $this->postJson('/api/v1/auth/initiate', [
        'identifier' => 'test@example.com',
        'type' => 'invalid'
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['type']);
});

test('otp verification requires valid identifier', function () {
    $response = $this->postJson('/api/v1/auth/otp/verify', [
        'identifier' => 'invalid-email',
        'type' => 'email',
        'otp' => '111111',
        'purpose' => 'LOGIN'
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['identifier']);
});

test('otp verification fails with wrong otp', function () {
    $user = User::factory()->withOtp('123456')->create();

    $response = $this->postJson('/api/v1/auth/otp/verify', [
        'identifier' => $user->email,
        'type' => 'email',
        'otp' => '111111',
        'purpose' => 'LOGIN'
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['otp']);
});

test('otp verification fails with expired otp', function () {
    $user = User::factory()->create();

    // Create expired OTP
    $user->otp()->create([
        'identifier' => $user->email,
        'type' => 'email',
        'code' => '123456',
        'expires_at' => now()->subMinutes(6)
    ]);

    $response = $this->postJson('/api/v1/auth/otp/verify', [
        'identifier' => $user->email,
        'type' => 'email',
        'otp' => '123456',
        'purpose' => 'LOGIN'
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['otp']);
});

test('password login requires valid password format', function () {
    $user = User::factory()->create();

    $response = $this->postJson('/api/v1/auth/login/password', [
        'identifier' => $user->email,
        'type' => 'email',
        'password' => '123' // too short
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

test('password reset requires password confirmation', function () {
    $user = User::factory()->create(['email' => 'test@example.com']);
    $token = Password::createToken($user);

    $response = $this->postJson('/api/v1/auth/password/reset/otp', [
        'identifier' => 'test@example.com',
        'type' => 'email',
        'token' => $token,
        'password' => 'newpassword123',
        // missing password_confirmation
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

test('password reset requires matching confirmation', function () {
    $user = User::factory()->create(['email' => 'test@example.com']);
    $token = Password::createToken($user);

    $response = $this->postJson('/api/v1/auth/password/reset/otp', [
        'identifier' => 'test@example.com',
        'type' => 'email',
        'token' => $token,
        'password' => 'newpassword123',
        'password_confirmation' => 'different123'
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

test('password reset requires valid token', function () {
    $user = User::factory()->create(['email' => 'test@example.com']);

    $response = $this->postJson('/api/v1/auth/password/reset/otp', [
        'identifier' => 'test@example.com',
        'type' => 'email',
        'token' => 'invalid-token',
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123'
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['token']);
});

test('logout requires authentication', function () {
    $response = $this->postJson('/api/v1/auth/logout');

    $response->assertStatus(401);
});
