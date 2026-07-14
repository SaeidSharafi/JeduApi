<?php

declare(strict_types=1);

namespace App\Notifications\Auth;

use App\Events\OtpPrepared;
use App\Notifications\SmsChannel;
use App\Notifications\SmsMessage;
use App\Services\IpPanelSmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

final class OtpSmsNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private IpPanelSmsService $smsService;

    public function __construct(
        protected OtpPrepared $otpCode,
    ) {
        $this->smsService = app(IpPanelSmsService::class);
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<string>
     */
    public function via(object $notifiable): string
    {
        return SmsChannel::class;
    }

    public function toSms(object $notifiable): SmsMessage
    {
        return (new SmsMessage)
            ->pattern('mdoe1j1587', ['code' => $this->otpCode->code])
            ->content(__('messages.auth.otp.sms_content'))
            ->type('OTP');
    }
}
