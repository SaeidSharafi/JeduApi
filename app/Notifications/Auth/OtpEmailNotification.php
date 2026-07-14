<?php

declare(strict_types=1);

namespace App\Notifications\Auth;

use App\Events\OtpPrepared;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class OtpEmailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected OtpPrepared $otpCode,
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
            ->subject(__('messages.auth.otp.email_subject'))
            ->line(__('messages.auth.otp.email_body', ['code' => $this->otpCode->code]))
            ->line(__('messages.auth.otp.email_expiry'))
            ->line(__('messages.auth.otp.email_ignore'));
    }
}
