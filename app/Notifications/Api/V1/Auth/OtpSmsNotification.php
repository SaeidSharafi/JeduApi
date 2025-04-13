<?php

namespace App\Notifications\Api\V1\Auth;

use App\Dto\OtpManager\SentOtpDto;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OtpSmsNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected SentOtpDto $otp
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Your Login OTP Code')
            ->line('Your OTP code is: ' . $this->otp->code)
            ->line('Your Phone Number is: ' . $notifiable->phone)
            ->line('This code will expire in 5 minutes.')
            ->line('If you did not request this code, please ignore this email.');
    }
}
