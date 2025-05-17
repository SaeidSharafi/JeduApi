<?php

declare(strict_types=1);

use App\Enums\OtpType;
use App\Events\OtpPrepared;
use App\Models\Admin;
use App\Models\User;
use App\Notifications\Auth\OtpEmailNotification;
use App\Notifications\Auth\OtpSmsNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Notification::fake();
});

test('OtpEmailNotification contains expected data', function (): void {
    $otp = '123456';
    $notification = new OtpEmailNotification($otp);
    $user = User::factory()->create(['email' => 'test@example.com']);

    $mailData = $notification->toMail($user);
    $mailView = $mailData->render();

    expect($mailData->subject)->toBe('Your Login OTP Code')
        ->and($mailView->toHtml())->toMatch("/\b{$otp}\b/m");
});
test('user email is set to use phone plus @example.com if email is null in testing environemnt', function (): void {
    $user = User::factory()->create(['phone' => '09321456987', 'email' => null]);

    event(
        new OtpPrepared(
            identifier: '09321456987',
            guard: 'user',
            code: '123456',
            type: OtpType::SIGNIN,
            trackingCode: 'test-tracking',
            params: []
        )
    );

    Notification::assertSentTo($user, OtpEmailNotification::class);
    Notification::assertSentTo($user, OtpSmsNotification::class);

});
test('OtpSmsNotification contains expected data', function (): void {
    $otp = '123456';
    $otp = new OtpPrepared('09321456987', 'user', $otp, OtpType::SIGNIN, 'test-tracking', []);
    $notification = new OtpSmsNotification($otp);
    $user = User::factory()->create(['phone' => '09321456987']);

    $mailData = $notification->toMail($user);
    $mailView = $mailData->render();

    expect($mailData->subject)->toBe('Your Login OTP Code')
        ->and($mailView->toHtml())->toMatch("/\b{$otp->code}\b/m")
        ->and($mailView->toHtml())->toMatch("/\b{$user->phone}\b/m");
});

test('notifications handle different guard types correctly', function (): void {
    $code = '123456';
    $identifier = '1234567890';
    $trackingCode = 'test-tracking';

    // Test user guard
    $userEvent = new OtpPrepared(
        identifier: $identifier,
        guard: 'user',
        code: $code,
        type: OtpType::SIGNIN,
        trackingCode: $trackingCode,
        params: []
    );
    $userNotification = new OtpSmsNotification($userEvent);
    $user = User::factory()->create(['phone' => $identifier]);

    // Test admin guard
    $adminEvent = new OtpPrepared(
        identifier: $identifier,
        guard: 'admin',
        code: $code,
        type: OtpType::SIGNIN,
        trackingCode: $trackingCode,
        params: []
    );
    $adminNotification = new OtpSmsNotification($adminEvent);
    $admin = Admin::factory()->create(['phone' => $identifier]);

    $userMailData = $userNotification->toMail($user);
    $adminMailData = $adminNotification->toMail($admin);
    expect($userEvent->guard)->toBe('user')
        ->and($adminEvent->guard)->toBe('admin')
        ->and($userMailData->subject)->toBe('Your Login OTP Code')
        ->and($userMailData->render()->toHtml())->toMatch("/\b{$code}\b/m")
        ->and($userMailData->render()->toHtml())->toMatch("/\b{$user->phone}\b/m")
        ->and($adminMailData->subject)->toBe('Your Login OTP Code')
        ->and($adminMailData->render()->toHtml())->toMatch("/\b{$code}\b/m")
        ->and($adminMailData->render()->toHtml())->toMatch("/\b{$admin->phone}\b/m");
});
