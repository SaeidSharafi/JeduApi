<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Notifications\Auth\OtpSmsNotification;
use App\Notifications\Order\RefundCompletedNotification;
use App\Services\IpPanelSmsService;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

final class SmsChannel
{
    public function __construct(private IpPanelSmsService $sms) {}

    /**
     * Send the given notification.
     *
     * @param  OtpSmsNotification|RefundCompletedNotification  $notification
     */
    public function send(object $notifiable, Notification $notification): void
    {
        if (! $to = $notifiable->routeNotificationFor('sms', $notification)) {
            return;
        }

        // Get the SmsMessage object from the notification
        $message = $notification->toSms($notifiable);

        if (! $message instanceof SmsMessage) {
            // Or throw an exception, depending on how strict you want to be
            Log::error('Notification did not return an SmsMessage object.', ['notification' => get_class($notification)]);

            return;
        }

        if ($message->pattern) {
            $this->sms->sendPattern(
                pattern: $message->pattern,
                parameters: $message->parameters,
                to: $to,
                message: $message->content,
                type: $message->type,
            );

            return;
        }
        if ($message->content) {
            $this->sms->send(
                to: [$to],
                message: $message->content,
                type: $message->type
            );
        }
    }
}
