<?php

namespace App\Notifications\Auth;

use App\Events\OtpPrepared;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OtpSmsNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected OtpPrepared $otpCode
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Login OTP Code')
            ->line('Your OTP code is: '.$this->otpCode->code)
            ->line('Your OTP code is: '.$this->otpCode->type?->value)
            ->line('Your Phone Number is: '.$notifiable->phone)
            ->line('This code will expire in 5 minutes.')
            ->line('If you did not request this code, please ignore this email.');
    }
}
