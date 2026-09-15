<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\Sms\SmsNotificationOptionEnum;
use App\Enums\Sms\SmsSkipReasonEnum;
use App\Enums\System\SettingKeyEnum;
use App\Notifications\Auth\OtpSmsNotification;
use App\Notifications\Order\RefundCompletedNotification;
use App\Services\IpPanelSmsService;
use App\Services\SettingsService;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

final class SmsChannel
{
    public function __construct(
        private IpPanelSmsService $sms,
        private SettingsService $settings,
    ) {}

    /**
     * Send the given notification.
     *
     * A message whose type maps to a configurable option obeys it: a disabled
     * option skips the send, a configured `pattern_code` wins over the free-text
     * content, and an option that needs a pattern but has none skips rather than
     * falling back to custom text (the provider filters it). Types without an
     * option stay ungated. Every skip is recorded on the send service, so this
     * gate and the gateway gate share one `sms_logs` shape.
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

        $option = SmsNotificationOptionEnum::fromLogType($message->type);

        // Pattern-only options need no free text, so the log content may be
        // empty; `sendPattern()` and the skip records still carry it.
        $content = $message->content ?? '';

        if ($option instanceof SmsNotificationOptionEnum) {
            $settings = $option->resolve($this->storedOptions()[$option->value] ?? null);

            if (! $settings['enabled']) {
                $this->sms->recordSkipped($to, $content, $message->type, SmsSkipReasonEnum::OPTION_DISABLED);

                return;
            }

            if ($settings['pattern_code'] !== '') {
                $this->sms->sendPattern(
                    pattern: $settings['pattern_code'],
                    parameters: $message->parameters,
                    to: $to,
                    message: $content,
                    type: $message->type,
                );

                return;
            }

            if (! $option->isConfigured($settings)) {
                $this->sms->recordSkipped($to, $content, $message->type, SmsSkipReasonEnum::PATTERN_MISSING);

                return;
            }

            if ($content === '') {
                $this->sms->recordSkipped($to, $content, $message->type, SmsSkipReasonEnum::EMPTY_MESSAGE);

                return;
            }
        }

        if ($message->content) {
            $this->sms->send(
                to: [$to],
                message: $message->content,
                type: $message->type
            );
        }
    }

    /**
     * The raw stored notification-option map, before any per-option defaults.
     *
     * @return array<string, mixed>
     */
    private function storedOptions(): array
    {
        $stored = $this->settings->get(SettingKeyEnum::SMS_NOTIFICATIONS);

        return is_array($stored) ? $stored : [];
    }
}
