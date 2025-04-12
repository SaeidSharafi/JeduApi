<?php

namespace Tests\Unit\Actions\Auth;

use App\Actions\Auth\InitiateAuthAction;
use App\Actions\Auth\PasswordLoginAction;
use App\Actions\Auth\ResetPasswordAction;
use App\Actions\GenerateOtpAction;
use App\Actions\VerifyOtpAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use App\Notifications\OtpEmailNotification;
use App\Notifications\OtpSmsNotification;
use Tests\TestCase;
use Illuminate\Validation\ValidationException;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
});

test('InitiateAuthAction returns correct action for user without password', function () {
    $action = new InitiateAuthAction();
    $user = User::factory()->create(['password' => null]);

    $result = $action->execute($user->email, 'email');

    expect($result)->toBe([
        'action' => 'OTP_LOGIN',
        'message' => 'Please request OTP to login.'
    ]);
});

test('InitiateAuthAction returns correct action for user with password', function () {
    $action = new InitiateAuthAction();
    $user = User::factory()->create(['password' => Hash::make('password')]);

    $result = $action->execute($user->email, 'email');

    expect($result)->toBe([
        'action' => 'PASSWORD_LOGIN',
        'message' => 'Please login with password.'
    ]);
});

test('GenerateOtpAction creates and sends OTP', function () {
    $action = new GenerateOtpAction();
    $user = User::factory()->create();

    $action->execute($user);

    $user->refresh();
    expect($user->otp)->not->toBeNull()
        ->and($user->otp_expires_at)->not->toBeNull();

    Notification::assertSentTo($user, OtpEmailNotification::class);
});

test('GenerateOtpAction sends SMS for users with phone numbers', function () {
    $action = new GenerateOtpAction();
    $user = User::factory()->create(['phone' => '1234567890']);

    $action->execute($user);

    Notification::assertSentTo($user, OtpSmsNotification::class);
});

test('VerifyOtpAction validates OTP correctly', function () {
    $action = new VerifyOtpAction();
    $otp = '123456';
    $user = User::factory()->create([
        'otp' => $otp,
        'otp_expires_at' => now()->addMinutes(5)
    ]);

    expect(fn () => $action->execute($user, $otp))->not->toThrow(ValidationException::class);
});

test('VerifyOtpAction rejects invalid OTP', function () {
    $action = new VerifyOtpAction();
    $user = User::factory()->create([
        'otp' => '123456',
        'otp_expires_at' => now()->addMinutes(5)
    ]);

    expect(fn () => $action->execute($user, 'wrong-otp'))
        ->toThrow(ValidationException::class, 'The OTP code is invalid or has expired.');
});

test('VerifyOtpAction rejects expired OTP', function () {
    $action = new VerifyOtpAction();
    $otp = '123456';
    $user = User::factory()->create([
        'otp' => $otp,
        'otp_expires_at' => now()->subMinutes(5)
    ]);

    expect(fn () => $action->execute($user, $otp))
        ->toThrow(ValidationException::class, 'The OTP code is invalid or has expired.');
});

test('PasswordLoginAction authenticates valid credentials', function () {
    $action = new PasswordLoginAction();
    $password = 'password123';
    $user = User::factory()->create([
        'password' => Hash::make($password)
    ]);

    $result = $action->execute($user->email, 'email', $password);

    expect($result)
        ->toHaveKey('access_token')
        ->toHaveKey('token_type')
        ->toHaveKey('user');
});

test('PasswordLoginAction rejects invalid credentials', function () {
    $action = new PasswordLoginAction();
    $user = User::factory()->create([
        'password' => Hash::make('correct-password')
    ]);

    expect(fn () => $action->execute($user->email, 'email', 'wrong-password'))
        ->toThrow(ValidationException::class, 'The provided credentials are incorrect.');
});

test('ResetPasswordAction updates password with valid token', function () {
    $action = new ResetPasswordAction();
    $resetToken = 'valid-token';
    $user = User::factory()->create([
        'reset_token' => Hash::make($resetToken)
    ]);

    $action->execute($user->email, 'email', $resetToken, 'new-password');

    $user->refresh();
    expect(Hash::check('new-password', $user->password))->toBeTrue()
        ->and($user->reset_token)->toBeNull();
});

test('ResetPasswordAction rejects invalid reset token', function () {
    $action = new ResetPasswordAction();
    $user = User::factory()->create([
        'reset_token' => Hash::make('valid-token')
    ]);

    expect(fn () => $action->execute($user->email, 'email', 'invalid-token', 'new-password'))
        ->toThrow(ValidationException::class, 'Invalid or expired reset token.');
});
