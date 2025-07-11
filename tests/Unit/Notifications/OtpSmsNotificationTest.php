<?php
beforeEach(function (): void {
    Notification::fake();
    config()->set('services.ippanel.from', 1000);
    config()->set('services.ippanel.api_key', "test_key");
    config()->set('services.ippanel.sand_box', false);
    Http::fake(
        [
            'https://api2.ippanel.com/api/v1/sms/pattern/normal/send' => Http::response(
                [
                    'status' => "OK",
                    'data'   => [
                        'message_id' => random_int(1000000000, 9999999999),
                    ],
                ],
            )
        ]
    );
});
it('send Sms', function (): void {
    $otp = '123456';
    $otp = new \App\Events\OtpPrepared('09321456987', 'user', $otp, \App\Enums\OtpType::SIGNIN, 'test-tracking', []);
    $notification = new \App\Notifications\Auth\OtpSmsNotification($otp);
    $user = \App\Models\User::factory()->create(['phone' => '09321456987']);

    $notification->toSms($user);

    $smsLog = \App\Models\SmsLog::latest()->first();
    expect($smsLog->status)->toBe(200)
        ->and($smsLog->content)->toBe("Your OTP code is: *****")
        ->and($smsLog->to)->toBe("09321456987")
        ->and($smsLog->from)->toBe('1000')
        ->and($smsLog->type)->toBe('OTP');
});
