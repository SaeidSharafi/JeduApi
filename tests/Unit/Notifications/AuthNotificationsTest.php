<?php

namespace Tests\Unit\Notifications;

use App\Models\Admin;
use App\Models\User;
use App\Notifications\OtpEmailNotification;
use App\Notifications\OtpSmsNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('OtpEmailNotification contains otp code', function (): void {
    $otp = '123456';
    $notification = new OtpEmailNotification($otp);
    $user = User::factory()->create();

    $mailMessage = $notification->toMail($user);
    $mailData = $mailMessage->data();

    expect($mailMessage->subject)->toBe('Your Login OTP Code')
        ->and($mailData['introLines'])->toBeArray()
        ->and($mailData['introLines'][0])->toContain($otp);
});

test('OtpSmsNotification builds correct message array', function (): void {
    $otp = '123456';
    $notification = new OtpSmsNotification($otp);
    $user = User::factory()->create(['phone' => '1234567890']);

    $message = $notification->toArray($user);

    expect($message)
        ->toHaveKey('phone', '1234567890')
        ->toHaveKey('message')
        ->and($message['message'])->toContain($otp);
});

test('notifications work with both User and Admin models', function (): void {
    $otp = '123456';
    $emailNotification = new OtpEmailNotification($otp);
    $smsNotification = new OtpSmsNotification($otp);

    $user = User::factory()->create(['phone' => '1234567890']);
    $admin = Admin::factory()->create(['phone' => '0987654321']);

    // Test email notifications
    $userMail = $emailNotification->toMail($user);
    $adminMail = $emailNotification->toMail($admin);

    $userMailData = $userMail->data();
    $adminMailData = $adminMail->data();

    expect($userMailData['introLines'][0])->toContain($otp)
        ->and($adminMailData['introLines'][0])->toContain($otp);

    // Test SMS notifications
    $userSms = $smsNotification->toArray($user);
    $adminSms = $smsNotification->toArray($admin);

    expect($userSms['phone'])->toBe('1234567890')
        ->and($adminSms['phone'])->toBe('0987654321')
        ->and($userSms['message'])->toContain($otp)
        ->and($adminSms['message'])->toContain($otp);
});
