<?php

declare(strict_types=1);

use App\Enums\System\OtpType;
use App\Models\Staff;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * @var $this TestCase
 */
beforeEach(function (): void {
    Notification::fake();

    $minOtpCode         = config('otp.code_min');
    $maxOtpCode         = config('otp.code_max');
    $this->otpCode      = random_int($minOtpCode, $maxOtpCode);
    $this->trackingCode = 'test-tracking';

    $fakeGenerator = $this->app->make(App\Contracts\OtpGeneratorInterface::class);
    if ($fakeGenerator instanceof Tests\Support\Fakes\FakeOtpGenerator) {
        $fakeGenerator->setNextOtpCode($this->otpCode)
            ->setNextTrackingCode($this->trackingCode);
    }
});

test('password login is rejected for a banned staff account', function (): void {
    Staff::factory()->create([
        'email'     => 'banned@example.com',
        'password'  => Hash::make('password123'),
        'is_banned' => true,
    ]);

    $this->postJson(route('api.v1.admin.auth.password-login'), [
        'identifier' => 'banned@example.com',
        'type'       => 'email',
        'password'   => 'password123',
    ])
        ->assertForbidden()
        ->assertJson(['message' => __('messages.auth.login.banned')]);
});

test('otp login is rejected for a banned staff account', function (): void {
    Staff::factory()->create([
        'email'     => 'banned@example.com',
        'is_banned' => true,
    ]);

    $this->postJson(route('api.v1.admin.auth.initiate'), [
        'identifier' => 'banned@example.com',
    ])->assertOk();

    $this->postJson(route('api.v1.admin.auth.otp-verify'), [
        'identifier'    => 'banned@example.com',
        'type'          => 'email',
        'otp_code'      => $this->otpCode,
        'tracking_code' => $this->trackingCode,
        'otp_type'      => OtpType::SIGNIN->value,
    ])
        ->assertForbidden()
        ->assertJson(['message' => __('messages.auth.login.banned')]);
});

test('unban restores password login', function (): void {
    $staff = Staff::factory()->create([
        'email'     => 'unbanned@example.com',
        'password'  => Hash::make('password123'),
        'is_banned' => true,
    ]);

    $staff->update(['is_banned' => false, 'banned_at' => null]);

    $this->postJson(route('api.v1.admin.auth.password-login'), [
        'identifier' => 'unbanned@example.com',
        'type'       => 'email',
        'password'   => 'password123',
    ])->assertOk();
});

test('unban restores otp login', function (): void {
    $staff = Staff::factory()->create([
        'email'     => 'unbanned@example.com',
        'is_banned' => true,
    ]);

    $staff->update(['is_banned' => false, 'banned_at' => null]);

    $this->postJson(route('api.v1.admin.auth.initiate'), [
        'identifier' => 'unbanned@example.com',
    ])->assertOk();

    $this->postJson(route('api.v1.admin.auth.otp-verify'), [
        'identifier'    => 'unbanned@example.com',
        'type'          => 'email',
        'otp_code'      => $this->otpCode,
        'tracking_code' => $this->trackingCode,
        'otp_type'      => OtpType::SIGNIN->value,
    ])->assertOk();
});

test('a banned staff account can still reset its password', function (): void {
    Staff::factory()->create([
        'email'     => 'banned@example.com',
        'password'  => Hash::make('old-password'),
        'is_banned' => true,
    ]);

    $this->postJson(route('api.v1.admin.auth.forgot-password'), [
        'identifier' => 'banned@example.com',
    ])->assertOk();

    $this->postJson(route('api.v1.admin.auth.password-reset'), [
        'identifier'            => 'banned@example.com',
        'tracking_code'         => $this->trackingCode,
        'otp_code'              => $this->otpCode,
        'password'              => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertOk();

    expect(Hash::check('new-password', Staff::where('email', 'banned@example.com')->first()->password))->toBeTrue();
});
