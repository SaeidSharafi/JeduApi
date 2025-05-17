<?php

declare(strict_types=1);

namespace App\Notifications\Auth;

use App\Events\OtpPrepared;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class OtpSmsNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected OtpPrepared $otpCode
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Login OTP Code')
            ->line('Your OTP code is: '.$this->otpCode->code)
            ->line('Your OTP code is: '.$this->otpCode->type?->identifier())
            ->line('Your Phone Number is: '.$notifiable->phone)
            ->line('This code will expire in 5 minutes.')
            ->line('If you did not request this code, please ignore this email.');
    }
}
