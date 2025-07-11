<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Notification;

final class SmsChannel
{
    /**
     * Send the given notification.
     */
    public function send(object $notifiable, Notification $notification): void
    {
        $notification->toSms($notifiable);
    }
}
