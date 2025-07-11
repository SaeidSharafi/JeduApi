<?php

declare(strict_types=1);

use App\Enums\OtpType;
use App\Events\OtpPrepared;
use App\Models\Staff;
use App\Models\User;
use App\Notifications\Auth\OtpEmailNotification;
use App\Notifications\Auth\OtpSmsNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Notification::fake();
    config()->set('services.ippanel.from', 1000);
    config()->set('services.ippanel.api_key', 'test_key');
    config()->set('services.ippanel.sand_box', false);
    Http::fake(
        [
            'https://api2.ippanel.com/api/v1/sms/pattern/normal/send' => Http::response(
                [
                    'status' => 'OK',
                    'data'   => [
                        'message_id' => random_int(1000000000, 9999999999),
                    ],
                ],
            ),
        ]
    );
});

test('OtpEmailNotification contains expected data', function (): void {
    $otp = new OtpPrepared(
        identifier: '09321456987',
        guard: 'user',
        code: '123456',
        type: OtpType::SIGNIN,
        trackingCode: 'test-tracking',
        params: []
    );

    $notification = new OtpEmailNotification($otp);
    $user         = User::factory()->create(['email' => 'test@example.com']);

    $mailData = $notification->toMail($user);
    $mailView = $mailData->render();

    expect($mailData->subject)->toBe('Your Login OTP Code')
        ->and($mailView->toHtml())->toMatch("/\b{$otp->code}\b/m");
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
    Notification::assertSentTo($user,
        OtpSmsNotification::class,
        function ($notification, $channels) {
            if (is_array($channels)) {
                return in_array(App\Notifications\SmsChannel::class, $channels);
            }

            return $channels === App\Notifications\SmsChannel::class;
        });

});

test('notifications handle different guard types correctly', function (): void {
    $code         = '123456';
    $identifier   = '1234567890';
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
    $userNotification = new OtpEmailNotification($userEvent);
    $user             = User::factory()->create(['phone' => $identifier]);

    // Test staff guard
    $staffEvent = new OtpPrepared(
        identifier: $identifier,
        guard: 'staff',
        code: $code,
        type: OtpType::SIGNIN,
        trackingCode: $trackingCode,
        params: []
    );
    $staffNotification = new OtpEmailNotification($staffEvent);
    $staff             = Staff::factory()->create(['phone' => $identifier]);

    $userMailData  = $userNotification->toMail($user);
    $staffMailData = $staffNotification->toMail($staff);
    expect($userEvent->guard)->toBe('user')
        ->and($staffEvent->guard)->toBe('staff')
        ->and($userMailData->subject)->toBe('Your Login OTP Code')
        ->and($userMailData->render()->toHtml())->toMatch("/\b{$code}\b/m")
        ->and($staffMailData->subject)->toBe('Your Login OTP Code')
        ->and($staffMailData->render()->toHtml())->toMatch("/\b{$code}\b/m");
});
