<?php

declare(strict_types=1);

use App\Events\OtpPrepared;
use App\Notifications\Auth\OtpSmsNotification;
use App\Notifications\SmsMessage;

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
describe('OTP SMS Notification', function (): void {
    it('creates the correct sms message payload for customer', function (): void {
        $user     = new App\Models\User();
        $otpEvent = new OtpPrepared(
            identifier: '09321456987',
            guard: 'user',
            code: '123456',
            type: \App\Enums\System\OtpType::SIGNIN,
            trackingCode: 'test-tracking',
            params: []
        );
        $notification = new OtpSmsNotification($otpEvent);

        $smsMessage = $notification->toSms($user);

        expect($smsMessage)->toBeInstanceOf(SmsMessage::class)
            ->and($smsMessage->pattern)->toBe('mdoe1j1587')
            ->and($smsMessage->parameters)->toBe(['code' => '123456'])
            ->and($smsMessage->type)->toBe('OTP');
    });

    it('creates the correct sms message payload for Staff', function (): void {
        $user     = new App\Models\Staff();
        $otpEvent = new OtpPrepared(
            identifier: '09321456987',
            guard: 'user',
            code: '123456',
            type: \App\Enums\System\OtpType::SIGNIN,
            trackingCode: 'test-tracking',
            params: []
        );
        $notification = new OtpSmsNotification($otpEvent);

        $smsMessage = $notification->toSms($user);

        expect($smsMessage)->toBeInstanceOf(SmsMessage::class)
            ->and($smsMessage->pattern)->toBe('mdoe1j1587')
            ->and($smsMessage->parameters)->toBe(['code' => '123456'])
            ->and($smsMessage->type)->toBe('OTP');
    });
});
