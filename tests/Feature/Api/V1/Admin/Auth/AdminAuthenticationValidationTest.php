<?php

declare(strict_types=1);

use App\Dto\OtpManager\OtpDto;
use App\Enums\OtpType;
use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $minOtpCode = config('otp.code_min');
    $maxOtpCode = config('otp.code_max');
    $this->OtpCode = random_int($minOtpCode, $maxOtpCode);
    $this->invalidOtpCode = $this->OtpCode + 1 > $maxOtpCode ? $this->OtpCode - 1 : $this->OtpCode + 1;
    $this->trackingCode = 'test-tracking';
});
test('admin auth requires valid email format', function (): void {
    $response = $this->postJson('/api/v1/admin/auth/initiate', [
        'identifier' => 'not-an-email',
        'type' => 'email',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['identifier']);
});

test('admin auth requires valid phone format when type is phone', function (): void {
    $response = $this->postJson('/api/v1/admin/auth/initiate', [
        'identifier' => 'not-a-phone',
        'type' => 'phone',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['identifier']);
});

test('admin otp request requires valid otp_type', function (): void {
    $admin = Admin::factory()->create();

    $response = $this->postJson(route('api.v1.admin.auth.otp-resend'), [
        'identifier' => $admin->email,
        'type' => 'email',
        'otp_type' => 'INVALID',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['otp_type']);
});

test('admin otp verification requires valid otp_type', function (): void {
    $admin = Admin::factory()->create();
    Cache::put('otp_admin@example.com_admin_value_SIGNIN', [
        'code' => $this->OtpCode,
        'tracking_code' => 'test-tracking',
    ], 300);

    $response = $this->postJson('/api/v1/admin/auth/otp/verify', [
        'identifier' => 'admin@example.com',
        'type' => 'email',
        'otp_code' => $this->OtpCode,
        'otp_type' => 'INVALID',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['otp_type']);
});

test('admin otp verification requires valid identifier', function (): void {
    $response = $this->postJson('/api/v1/admin/auth/otp/verify', [
        'identifier' => 'invalid-email',
        'type' => 'email',
        'otp_code' => $this->OtpCode,
        'otp_type' => OtpType::SIGNIN->value,
        'tracking_code' => $this->trackingCode,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['identifier']);
});

test('admin otp verification fails with wrong otp', function (): void {
    $admin = Admin::factory()->create(['email' => 'admin@example.com']);
    Cache::put('otp_admin@example.com_admin_value_SIGNIN',
        new OtpDto($this->OtpCode, $this->trackingCode), 300);

    $response = $this->postJson('/api/v1/admin/auth/otp/verify', [
        'identifier' => 'admin@example.com',
        'type' => 'email',
        'otp_code' => $this->invalidOtpCode,
        'tracking_code' => 'test-tracking',
        'otp_type' => OtpType::SIGNIN->value,
    ]);

    $response->assertStatus(422);
});

test('admin otp verification fails with expired otp', function (): void {
    $admin = Admin::factory()->create(['email' => 'admin@example.com']);
    // Put expired OTP in cache
    Cache::put('otp_admin@example.com_admin_value_SIGNIN', [
        'code' => $this->OtpCode,
        'tracking_code' => 'test-tracking',
    ], -1); // Expired

    $response = $this->postJson('/api/v1/admin/auth/otp/verify', [
        'identifier' => 'admin@example.com',
        'type' => 'email',
        'otp_code' => $this->OtpCode,
        'tracking_code' => 'test-tracking',
        'otp_type' => OtpType::SIGNIN->value,
    ]);

    $response->assertStatus(422);
});

test('admin password login requires valid credentials', function (): void {
    $admin = Admin::factory()->create([
        'email' => 'admin@example.com',
        'password' => Hash::make('correct-password'),
    ]);

    $response = $this->postJson('/api/v1/admin/auth/login/password', [
        'identifier' => 'admin@example.com',
        'type' => 'email',
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

test('admin auth with non-existent account returns proper error', function (): void {
    $response = $this->postJson('/api/v1/admin/auth/otp/resend', [
        'identifier' => 'nonexistent@example.com',
        'type' => 'email',
        'otp_type' => OtpType::SIGNIN->value,
    ]);

    $response->assertNotFound()
        ->assertJson([
            'message' => 'User not found',
        ]);
});

test('admin logout requires valid auth token', function (): void {
    $response = $this->postJson('/api/v1/admin/auth/logout');
    $response->assertStatus(500)
        ->assertJson([
            'message' => 'Unauthenticated.',
        ]);
});

test('admin cannot use invalid auth token', function (): void {
    $response = $this->withHeader('Authorization', 'Bearer invalid-token')
        ->postJson('/api/v1/admin/auth/logout');

    $response->assertStatus(500)
        ->assertJson([
            'message' => 'Unauthenticated.',
        ]);
});
