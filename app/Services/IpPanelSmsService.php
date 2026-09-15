<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Sms\SmsGatewayEnum;
use App\Enums\Sms\SmsSkipReasonEnum;
use App\Models\SmsLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class IpPanelSmsService
{
    private string $baseUrl = 'https://api2.ippanel.com/api/v1';

    private ?string $apiKey = null;

    private null|int|string $from = null;

    public function __construct(private readonly SettingsService $settings) {}

    public function setApiKey(string $apiKey): void
    {
        $this->apiKey = $apiKey;
    }

    public function setFrom(int|string $from): void
    {
        $this->from = $from;
    }

    /**
     * Record an attempt a notification option blocked before the gateway was
     * consulted.
     *
     * Public because `SmsChannel` owns the option gate; both gates write the
     * same `sms_logs` shape, including the sender the attempt would have used.
     *
     * @param  array<int, string>|string  $to
     */
    public function recordSkipped(array|string $to, string $content, string $type, SmsSkipReasonEnum $reason): void
    {
        $this->writeSkip($to, $content, $type, $this->senderFrom($this->resolveGatewaySettings()), $reason);
    }

    /**
     * @param  array<int, string>  $to
     */
    public function send(array $to, string $message, string $type = 'custom'): void
    {
        $config = $this->sendConfig($to, $message, $type);

        if ($config === null) {
            return;
        }

        if ($config['sandbox']) {
            $this->record(
                status: 200,
                data: ['message_id' => 'Sandbox_'.randomNumber(10)],
                content: $message,
                type: $type,
                to: $to,
                from: $config['from'],
            );

            return;
        }

        $response = Http::baseUrl($this->baseUrl)
            ->withHeaders([
                'apikey' => $config['api_key'],
            ])
            ->post(
                '/sms/send/webservice/single',
                [
                    'sender'    => $config['from'],
                    'recipient' => $to,
                    'message'   => $message,
                ]
            );

        $this->record($response->status(), $response->json(), $message, $type, $to, $config['from']);

        if ($response->failed()) {
            Log::error(
                'SMS sending failed',
                [
                    'status'  => $response->status(),
                    'message' => $response->json(),
                    'to'      => implode(',', $to),
                    'from'    => $config['from'],
                ]
            );
            $response->throw();
        }
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function sendPattern(string $pattern, array $parameters, string $to, string $message = '', string $type = 'pattern'): void
    {
        $config = $this->sendConfig($to, $message, $type);

        if ($config === null) {
            return;
        }

        if ($config['sandbox']) {
            $this->record(
                status: 200,
                data: [
                    'pattern'    => $pattern,
                    'parameters' => $parameters,
                    'message_id' => 'Sandbox_'.randomNumber(10),
                ],
                content: $message,
                type: $type,
                to: $to,
                from: $config['from'],
            );

            return;
        }

        $response = Http::baseUrl($this->baseUrl)
            ->withHeaders([
                'apikey' => $config['api_key'],
            ])
            ->post(
                '/sms/pattern/normal/send',
                [
                    'code'      => $pattern,
                    'sender'    => $config['from'],
                    'recipient' => $to,
                    'variable'  => $parameters,
                ]
            );

        $this->record($response->status(), $response->json(), $message, $type, $to, $config['from']);

        if ($response->failed()) {
            Log::error(
                'SMS sending failed',
                [
                    'pattern' => $pattern,
                    'type'    => $type,
                    'status'  => $response->status(),
                    'message' => $response->json(),
                    'to'      => $to,
                    'from'    => $config['from'],
                ]
            );
            $response->throw();
        }
    }

    /**
     * Resolve the credentials for one send, or record the attempt and return
     * `null` when the send must not be attempted.
     *
     * The gateway switch is a kill switch: a switched-off gateway blocks every
     * send before any HTTP call — including login codes — and the attempt is
     * recorded as skipped instead of throwing inside a queued notification job.
     * An unconfigured gateway is recorded the same way, so a missing credential
     * never surfaces as an unhandled queued-job error.
     *
     * @param  array<int, string>|string  $to
     * @return array{api_key: string, from: string, sandbox: bool}|null
     */
    private function sendConfig(array|string $to, string $message, string $type): ?array
    {
        $gateway = $this->resolveGatewaySettings();

        $apiKey = $this->apiKey ?? $gateway['api_key'];
        $from   = $this->senderFrom($gateway);

        if (! $gateway['enabled']) {
            $this->writeSkip($to, $message, $type, $from, SmsSkipReasonEnum::GATEWAY_DISABLED);

            return null;
        }

        if ($apiKey === null || $apiKey === '' || $from === '') {
            $this->writeSkip($to, $message, $type, $from, SmsSkipReasonEnum::NOT_CONFIGURED);

            return null;
        }

        return [
            'api_key' => $apiKey,
            'from'    => $from,
            'sandbox' => $gateway['sandbox'],
        ];
    }

    /**
     * Resolve the gateway credentials the send path uses.
     *
     * The stored `SettingKeyEnum::SMS_IPPANEL` row wins where it declares a
     * field (see `SmsGatewayEnum::resolvedSettings()`), so a key saved through
     * the settings API takes effect on the next send without a deployment; a
     * never-saved gateway falls back to `config/sms.php`.
     *
     * @return array{enabled: bool, from: string, api_key: string|null, sandbox: bool}
     */
    private function resolveGatewaySettings(): array
    {
        $gateway  = SmsGatewayEnum::IPPANEL;
        $settings = $gateway->resolvedSettings($this->settings->get($gateway->settingKey()));

        $apiKey = $settings['api_key'] ?? null;

        return [
            'enabled' => (bool) ($settings['enabled'] ?? false),
            'from'    => (string) ($settings['from'] ?? ''),
            'api_key' => is_string($apiKey) ? $apiKey : null,
            'sandbox' => (bool) ($settings['sandbox'] ?? false),
        ];
    }

    /**
     * The sender a send or skip would use, honouring a runtime override.
     *
     * @param  array{enabled: bool, from: string, api_key: string|null, sandbox: bool}  $gateway
     */
    private function senderFrom(array $gateway): string
    {
        return (string) ($this->from ?? $gateway['from']);
    }

    /**
     * @param  array<int, string>|string  $to
     */
    private function writeSkip(array|string $to, string $message, string $type, string $from, SmsSkipReasonEnum $reason): void
    {
        $this->record(SmsLog::STATUS_SKIPPED, ['reason' => $reason->value], $message, $type, $to, $from);
    }

    /**
     * @param  array<int, string>|string  $to
     */
    private function record(int $status, mixed $data, string $content, string $type, array|string $to, string $from): void
    {
        SmsLog::create([
            'status'  => $status,
            'data'    => $data,
            'content' => $content,
            'type'    => $type,
            'to'      => $to,
            'from'    => $from,
            'sent_at' => now(),
        ]);
    }
}
