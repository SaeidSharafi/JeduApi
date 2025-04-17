<?php

namespace App\Notifications\Auth;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OtpEmailNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected string $otpCode
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Login OTP Code')
            ->line('Your OTP code is: '.$this->otpCode)
            ->line('This code will expire in 5 minutes.')
            ->line('If you did not request this code, please ignore this email.');
    }
}
