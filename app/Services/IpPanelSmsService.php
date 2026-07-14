<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SmsLog;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class IpPanelSmsService
{
    private string $baseUrl = 'https://api2.ippanel.com/api/v1';

    private ?string $apiKey;

    private null|int|string $from;

    public function __construct()
    {
        $this->apiKey = config('services.ippanel.api_key');
        $this->from   = config('services.ippanel.from');
    }

    public function setApiKey(string $apiKey): void
    {
        $this->apiKey = $apiKey;
    }

    public function setFrom(int|string $from): void
    {
        $this->from = $from;
    }

    public function send(array $to, string $message, string $type = 'custom'): void
    {
        $this->validateConfig();
        if (config('services.ippanel.sand_box')) {
            SmsLog::create([
                'status' => 200,
                'data'   => [
                    'message_id' => 'Sandbox_'.randomNumber(10),
                ],
                'content' => $message,
                'type'    => $type,
                'to'      => $to,
                'from'    => $this->from,
                'sent_at' => now(),
            ]);

            return;
        }
        $response = Http::baseUrl($this->baseUrl)
            ->withHeaders([
                'apikey' => $this->apiKey,
            ])
            ->post(
                '/sms/send/webservice/single',
                [
                    'sender'    => $this->from,
                    'recipient' => $to,
                    'message'   => $message,
                ]
            );

        SmsLog::create([
            'status'  => $response->status(),
            'data'    => $response->json(),
            'content' => $message,
            'type'    => $type,
            'to'      => $to,
            'from'    => $this->from,
            'sent_at' => now(),
        ]);

        if ($response->failed()) {
            Log::error(
                'SMS sending failed',
                [
                    'status'  => $response->status(),
                    'message' => $response->json(),
                    'to'      => implode(',', $to),
                    'from'    => $this->from,
                ]
            );
            $response->throw();
        }
    }

    public function sendPattern(string $pattern, array $parameters, string $to, $message = '', $type = 'pattern'): void
    {
        $this->validateConfig();
        if (config('services.ippanel.sand_box')) {
            SmsLog::create([
                'status' => 200,
                'data'   => [
                    'pattern'    => $pattern,
                    'parameters' => $parameters,
                    'message_id' => 'Sandbox_'.randomNumber(10),
                ],
                'content' => $message,
                'type'    => $type,
                'to'      => $to,
                'from'    => $this->from,
                'sent_at' => now(),
            ]);

            return;
        }
        $response = Http::baseUrl($this->baseUrl)
            ->withHeaders([
                'apikey' => $this->apiKey,
            ])
            ->post(
                '/sms/pattern/normal/send',
                [
                    'code'      => $pattern,
                    'sender'    => $this->from,
                    'recipient' => $to,
                    'variable'  => $parameters,
                ]
            );

        SmsLog::create([
            'status'  => $response->status(),
            'data'    => $response->json(),
            'content' => $message,
            'type'    => $type,
            'to'      => $to,
            'from'    => $this->from,
            'sent_at' => now(),
        ]);

        if ($response->failed()) {
            Log::error(
                'SMS sending failed',
                [
                    'pattern' => $pattern,
                    'type'    => $type,
                    'status'  => $response->status(),
                    'message' => $response->json(),
                    'to'      => $to,
                    'from'    => $this->from,
                ]
            );
            $response->throw();
        }
    }

    private function validateConfig(): void
    {
        if (is_null($this->apiKey) || is_null($this->from)) {
            throw new Exception(__('messages.sms.ippanel_not_configured'));
        }

    }
}
