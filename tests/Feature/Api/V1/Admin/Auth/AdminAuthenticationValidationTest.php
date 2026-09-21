<?php

declare(strict_types=1);

use App\Enums\System\OtpType;
use App\Models\Staff;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    $minOtpCode           = config('otp.code_min');
    $maxOtpCode           = config('otp.code_max');
    $this->OtpCode        = random_int($minOtpCode, $maxOtpCode);
    $this->invalidOtpCode = $this->OtpCode + 1 > $maxOtpCode ? $this->OtpCode - 1 : $this->OtpCode + 1;
    $this->trackingCode   = 'test-tracking';
});
test('staff auth requires valid email format', function (): void {
    $response = $this->postJson('/api/v1/admin/auth/initiate', [
        'identifier' => 'not-an-email',
        'type'       => 'email',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['identifier']);
});

test('staff auth requires valid phone format when type is phone', function (): void {
    $response = $this->postJson('/api/v1/admin/auth/initiate', [
        'identifier' => 'not-a-phone',
        'type'       => 'phone',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['identifier']);
});

test('staff otp request requires valid otp_type', function (): void {
    $staff = Staff::factory()->create();

    $response = $this->postJson(route('api.v1.admin.auth.otp-resend'), [
        'identifier' => $staff->email,
        'type'       => 'email',
        'otp_type'   => 'INVALID',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['otp_type']);
});

test('staff otp verification requires valid otp_type', function (): void {
    $staff = Staff::factory()->create();
    putCachedOtp($staff->phone, 'staff', OtpType::SIGNIN, $this->OtpCode, 'test-tracking');

    $response = $this->postJson('/api/v1/admin/auth/otp/verify', [
        'identifier' => 'staff@example.com',
        'type'       => 'email',
        'otp_code'   => $this->OtpCode,
        'otp_type'   => 'INVALID',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['otp_type']);
});

test('staff otp verification requires valid identifier', function (): void {
    $response = $this->postJson('/api/v1/admin/auth/otp/verify', [
        'identifier'    => 'invalid-email',
        'type'          => 'email',
        'otp_code'      => $this->OtpCode,
        'otp_type'      => OtpType::SIGNIN->value,
        'tracking_code' => $this->trackingCode,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['identifier']);
});

test('staff otp verification fails with wrong otp', function (): void {
    $staff = Staff::factory()->create(['email' => 'staff@example.com']);
    putCachedOtp($staff->phone, 'staff', OtpType::SIGNIN, $this->OtpCode, $this->trackingCode);

    $response = $this->postJson('/api/v1/admin/auth/otp/verify', [
        'identifier'    => 'staff@example.com',
        'type'          => 'email',
        'otp_code'      => $this->invalidOtpCode,
        'tracking_code' => 'test-tracking',
        'otp_type'      => OtpType::SIGNIN->value,
    ]);

    $response->assertStatus(422);
});

test('staff otp verification fails with expired otp', function (): void {
    $staff = Staff::factory()->create(['email' => 'staff@example.com']);

    // A code that is still stored but whose send marker is far in the past.
    putCachedOtp(
        $staff->phone,
        'staff',
        OtpType::SIGNIN,
        $this->OtpCode,
        'test-tracking',
        now()->subDay()->timestamp,
    );

    $response = $this->postJson('/api/v1/admin/auth/otp/verify', [
        'identifier'    => 'staff@example.com',
        'type'          => 'email',
        'otp_code'      => $this->OtpCode,
        'tracking_code' => 'test-tracking',
        'otp_type'      => OtpType::SIGNIN->value,
    ]);

    $response->assertStatus(422);
});

test('staff password login requires valid credentials', function (): void {
    $staff = Staff::factory()->create([
        'email'    => 'staff@example.com',
        'password' => Hash::make('correct-password'),
    ]);

    $response = $this->postJson('/api/v1/admin/auth/login/password', [
        'identifier' => 'staff@example.com',
        'type'       => 'email',
        'password'   => 'wrong-password',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

test('staff auth with non-existent account returns proper error', function (): void {
    $response = $this->postJson('/api/v1/admin/auth/otp/resend', [
        'identifier' => 'nonexistent@example.com',
        'type'       => 'email',
        'otp_type'   => OtpType::SIGNIN->value,
    ]);

    $response->assertNotFound()
        ->assertJson([
            'message' => __('messages.auth.login.not_found'),
        ]);
});

test('staff logout requires valid auth token', function (): void {
    $response = $this->postJson('/api/v1/admin/auth/logout');
    $response->assertStatus(401)
        ->assertJson([
            'message' => __('messages.unauthorized'),
        ]);
});

test('staff cannot use invalid auth token', function (): void {
    $response = $this->withHeader('Authorization', 'Bearer invalid-token')
        ->postJson('/api/v1/admin/auth/logout');

    $response->assertStatus(401)
        ->assertJson([
            'message' => __('messages.unauthorized'),
        ]);
});

test('staff initiate auth is rate limited', function (): void {
    config()->set('otp.rate_limiting.initiate.max_attempts', 1);
    config()->set('otp.rate_limiting.initiate.decay_minutes', 1);

    $this->postJson('/api/v1/admin/auth/initiate', [
        'identifier' => 'missing-staff@example.com',
    ])->assertStatus(404);

    $this->postJson('/api/v1/admin/auth/initiate', [
        'identifier' => 'missing-staff@example.com',
    ])->assertStatus(429);
});

test('staff otp resend requires valid identifier format', function (): void {
    $response = $this->postJson('/api/v1/admin/auth/otp/resend', [
        'identifier' => 'invalid-identifier',
        'otp_type'   => OtpType::SIGNIN->value,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['identifier']);
});
