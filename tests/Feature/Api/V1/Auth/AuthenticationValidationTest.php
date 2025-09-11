<?php

declare(strict_types=1);

use App\Data\OtpManager\OtpDto;
use App\Enums\OtpType;
use App\Models\User;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;


beforeEach(function (): void {
    $minOtpCode           = config('otp.code_min');
    $maxOtpCode           = config('otp.code_max');
    $this->otpCode        = random_int($minOtpCode, $maxOtpCode);
    $this->invalidOtpCode = $this->otpCode + 1 > $maxOtpCode ? $this->otpCode - 1 : $this->otpCode + 1;
    $this->trackingCode   = 'test-tracking';
});

test('initiate auth requires valid email format', function (): void {
    $response = $this->postJson('/api/v1/auth/initiate', [
        'identifier' => 'not-an-email',
        'type'       => 'email',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['identifier']);
});

test('initiate auth requires valid phone format when type is phone', function (): void {
    $response = $this->postJson('/api/v1/auth/initiate', [
        'identifier' => 'not-a-phone',
        'type'       => 'phone',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['identifier']);
});

test('otp request requires valid otp_type', function (): void {
    $user = User::factory()->create();

    $response = $this->postJson('/api/v1/auth/otp/resend', [
        'identifier' => $user->email,
        'otp_type'   => 'INVALID',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['otp_type']);
});

test('otp verification requires valid otp_type', function (): void {
    $user = User::factory()->create();
    Cache::put('otp_test@example.com_user_value_SIGNIN', new OtpDto($this->otpCode, $this->trackingCode), 300);

    $response = $this->postJson('/api/v1/auth/otp/verify', [
        'identifier' => 'test@example.com',
        'type'       => 'email',
        'otp'        => '123456',
        'otp_type'   => 'INVALID',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['otp_type']);
});

test('otp verification fails with wrong otp', function (): void {
    $user = User::factory()->create(['email' => 'test@example.com']);
    Cache::put('otp_test@example.com_user_value_SIGNIN', new OtpDto($this->otpCode, $this->trackingCode), 300);

    $response = $this->postJson('/api/v1/auth/otp/verify', [
        'identifier'    => 'test@example.com',
        'type'          => 'email',
        'otp_code'      => $this->invalidOtpCode,
        'tracking_code' => 'test-tracking',
        'otp_type'      => OtpType::SIGNIN->value,
    ]);

    $response->assertStatus(422);
});

test('otp verification fails after max attempts', function (): void {
    $user = User::factory()->create(['email' => 'test@example.com']);
    Cache::put("otp_{$user->phone}_user_value_SIGNIN", new OtpDto($this->otpCode, $this->trackingCode), 300);

    // Try multiple times
    for ($i = 0; $i < 4; $i++) {
        $response = $this->postJson('/api/v1/auth/otp/verify', [
            'identifier'    => 'test@example.com',
            'type'          => 'email',
            'otp_code'      => $this->otpCode,
            'tracking_code' => 'test-tracking',
            'otp_type'      => OtpType::SIGNIN->value,
        ]);
    }

    $response->assertStatus(422);

    // Verify OTP has been deleted after max attempts
    expect(Cache::get("otp_{$user->phone}_user_value_SIGNIN"))->toBeNull();
});

test('password login requires valid credentials', function (): void {
    $user = User::factory()->create([
        'email'    => 'test@example.com',
        'password' => Hash::make('correct-password'),
    ]);

    $response = $this->postJson('/api/v1/auth/login/password', [
        'identifier' => 'test@example.com',
        'type'       => 'email',
        'password'   => 'wrong-password',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

test('logout requires authentication', function (): void {
    $response = $this->postJson('/api/v1/auth/logout');
    $response->assertStatus(401);
});

test('cannot use invalid auth token', function (): void {
    $response = $this->withHeader('Authorization', 'Bearer invalid-token')
        ->postJson('/api/v1/auth/logout');

    $response->assertStatus(401);
});
