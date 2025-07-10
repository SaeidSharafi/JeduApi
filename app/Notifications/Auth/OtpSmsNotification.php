<?php

declare(strict_types=1);

namespace App\Notifications\Auth;

use App\Events\OtpPrepared;
use App\Notifications\SmsChannel;
use App\Services\IpPanelSmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class OtpSmsNotification extends Notification
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

    public function toSms(object $notifiable): void
    {
        $this->smsService->sendPattern(
            pattern: 'mdoe1j1587',
            parameters: [
                'code' => $this->otpCode->code
            ],
            to: $notifiable->phone,
            messeage: 'Your OTP code is: *****',
            type: "OTP"
        );
    }
}
