<?php

declare(strict_types=1);

use App\Models\Staff;
use App\Models\User;
use App\Notifications\Auth\OtpEmailNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();

    // Setup test OTP data
    $minOtpCode           = config('otp.code_min');
    $maxOtpCode           = config('otp.code_max');
    $this->otpCode        = random_int($minOtpCode, $maxOtpCode);
    $this->invalidOtpCode = $this->otpCode + 1 > $maxOtpCode ? $this->otpCode - 1 : $this->otpCode + 1;
    $this->trackingCode   = 'test-tracking';

    $fakeGenerator = $this->app->make(App\Contracts\OtpGeneratorInterface::class);
    if ($fakeGenerator instanceof Tests\Support\Fakes\FakeOtpGenerator) {
        $fakeGenerator->setNextOtpCode($this->otpCode)
            ->setNextTrackingCode($this->trackingCode);
    }
});
test('staff can request password reset otp', function (): void {
    $staff = Staff::factory()->create([
        'email'    => 'staff1@example.com',
        'phone'    => '09301234567',
        'password' => Hash::make('oldpassword'),
    ]);

    $response = $this->postJson(route('api.v1.admin.auth.forgot-password'), [
        'identifier' => 'staff1@example.com',
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

    Notification::assertSentTo($staff, OtpEmailNotification::class);
});

test('staff without password cannot request password reset', function (): void {
    Staff::factory()->create([
        'email'    => 'staff2@example.com',
        'phone'    => '09301234567',
        'password' => null,
    ]);

    $response = $this->postJson(route('api.v1.admin.auth.forgot-password'), [
        'identifier' => 'staff2@example.com',
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'message' => __('messages.auth.doesnot_have_password'),
        ]);
});

test('non existent staff cannot request password reset', function (): void {
    $response = $this->postJson(route('api.v1.admin.auth.forgot-password'), [
        'identifier' => 'nonexistent@example.com',
    ]);

    $response->assertNotFound()
        ->assertJson([
            'message' => __('messages.auth.login.not_found'),
        ]);
});
test('staff without password cannot reset password', function (): void {
    Staff::factory()->create([
        'email'    => 'user2@example.com',
        'password' => null,
    ]);

    $response = $this->postJson(route('api.v1.admin.auth.password-reset'), [
        'identifier'            => 'user2@example.com',
        'tracking_code'         => $this->trackingCode,
        'otp_code'              => $this->otpCode, // Doesn't matter, user won't be found first
        'password'              => 'newpassword',
        'password_confirmation' => 'newpassword',
    ]);
    $response->assertStatus(422)
        ->assertJson([
            'message' => __('messages.auth.doesnot_have_password'),
        ]);
});
test('staff can reset password with valid otp', function (): void {
    $staff = Staff::factory()->create([
        'email'    => 'staff3@example.com',
        'phone'    => '09301234567',
        'password' => Hash::make('oldpassword'),
    ]);

    $forgotResponse = $this->postJson(route('api.v1.admin.auth.forgot-password'), [
        'identifier' => 'staff3@example.com',
    ]);

    $trackingCode = $this->trackingCode;

    // Then reset password with OTP
    $response = $this->postJson(route('api.v1.admin.auth.password-reset'), [
        'identifier'            => 'staff3@example.com',
        'tracking_code'         => $trackingCode,
        'otp_code'              => $this->otpCode,
        'password'              => 'newpassword',
        'password_confirmation' => 'newpassword',
    ]);

    $response
        ->assertOk()
        ->assertJson([
            'message'  => __('messages.success'),
            'data'     => 'Password reset OTP sent successfully.',
            'metadata' => [],
        ]);

    // Verify password was actually changed
    $staff->refresh();
    expect(Hash::check('newpassword', $staff->password))->toBeTrue();
});

test('staff cannot reset password with invalid otp', function (): void {
    $staff = Staff::factory()->create([
        'email'    => 'staff4@example.com',
        'phone'    => '09301234567',
        'password' => Hash::make('oldpassword'),
    ]);

    // First request the OTP
    $forgotResponse = $this->postJson(route('api.v1.admin.auth.forgot-password'), [
        'identifier' => 'staff4@example.com',
    ]);

    $trackingCode = $this->trackingCode;

    // Try resetting with invalid OTP
    $response = $this->postJson(route('api.v1.admin.auth.password-reset'), [
        'identifier'            => 'staff4@example.com',
        'tracking_code'         => $trackingCode,
        'otp_code'              => $this->invalidOtpCode,
        'password'              => 'newpassword',
        'password_confirmation' => 'newpassword',
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'message' => __('messages.auth.otp.invalid_code'),
        ]);

    // Verify password was not changed
    $staff->refresh();
    expect(Hash::check('oldpassword', $staff->password))->toBeTrue();
});
