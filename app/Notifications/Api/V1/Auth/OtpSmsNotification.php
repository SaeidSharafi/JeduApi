<?php

namespace App\Notifications\Api\V1\Auth;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OtpSmsNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected string $otp
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['array']; // Dummy channel that just stores the notification
    }

    public function toArray(object $notifiable): array
    {
        return [
            'phone' => $notifiable->phone,
            'message' => "Your OTP code is: {$this->otp}. Valid for 5 minutes.",
        ];
    }
}
