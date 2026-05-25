<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Enums\System\SettingKeyEnum;
use App\Exceptions\Integrations\ExternalProvisioningException;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class ImsService
{
    private string $baseUrl = '';

    private string $apiKey = '';

    private int $timeout = 30;

    public function __construct(private readonly SettingsService $settings)
    {
        $this->resolveConfig();
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function setConfig(array $config): void
    {
        $this->baseUrl = (string) ($config['base_url'] ?? '');
        $this->apiKey  = (string) ($config['api_key'] ?? '');
        $this->timeout = (int) ($config['timeout'] ?? 30);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function storeSetudent(array $payload): array
    {
        $this->assertConfigured();
        $response = Http::baseUrl($this->baseUrl)
            ->timeout(15)
            ->acceptJson()
            ->withToken($this->apiKey)
            ->post('/api/v2/student', $payload);

        if ($response->failed()) {
            throw $this->buildException($response, '/api/v2/student');
        }

        $responseData = $response->json();

        return is_array($responseData) ? $responseData : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function storeEnrolment(User $user, array $payload): array
    {
        $this->assertConfigured();
        $response = Http::baseUrl($this->baseUrl)
            ->timeout(15)
            ->acceptJson()
            ->withToken($this->apiKey)
            ->post('/api/v2/enrolment/'.$user->civil_id, $payload);

        if ($response->failed()) {
            throw $this->buildException($response, '/api/v2/enrolment/{civil_id}');
        }

        $responseData = $response->json();

        return is_array($responseData) ? $responseData : [];
    }

    /**
     * @param  array<string, string[]>  $rawErrors
     * @return array<string, string[]>
     */
    private function sanitizeErrors(array $rawErrors): array
    {
        $sanitized = [];
        foreach ($rawErrors as $field => $messages) {
            if (is_array($messages)) {
                $sanitized[$field] = array_map(fn (string $msg) => $this->sanitizeBody($msg), $messages);
            } else {
                $sanitized[$field] = [$this->sanitizeBody((string) $messages)];
            }
        }

        return $sanitized;
    }

    private function sanitizeBody(string $body): string
    {
        $sanitized = preg_replace(
            ['/\b[\w.+-]+@[\w-]+\.[\w.-]+\b/', '/\b09\d{9}\b/', '/\b\d{10}\b/'],
            '[REDACTED]',
            $body
        );

        return mb_substr($sanitized ?? $body, 0, 500);
    }

    private function buildException(Response $response, string $endpoint): ExternalProvisioningException
    {
        $status = $response->status();

        if ($status === 422) {
            $rawErrors    = data_get($response->json(), 'errors', []);
            $flatMessages = [];
            foreach ($rawErrors as $field => $fieldErrors) {
                if (is_array($fieldErrors)) {
                    foreach ($fieldErrors as $error) {
                        $flatMessages[] = "$field: ".$this->sanitizeBody((string) $error);
                    }
                } else {
                    $flatMessages[] = "$field: ".$this->sanitizeBody((string) $fieldErrors);
                }
            }
            $message = ! empty($flatMessages)
                ? implode('; ', $flatMessages)
                : 'IMS provisioning response was not successful.';

            return new ExternalProvisioningException($message, 0, null, [
                'http_status'       => $status,
                'endpoint'          => $endpoint,
                'validation_errors' => $this->sanitizeErrors($rawErrors),
                'raw_body_snippet'  => $this->sanitizeBody($response->body()),
            ]);
        }

        $genericMessage = str_contains($endpoint, 'enrol')
            ? 'IMS enrolment creation request failed.'
            : 'IMS provisioning request failed.';

        return new ExternalProvisioningException($genericMessage, 0, null, [
            'http_status'       => $status,
            'endpoint'          => $endpoint,
            'validation_errors' => [],
            'raw_body_snippet'  => $this->sanitizeBody($response->body()),
        ]);
    }

    private function resolveConfig(): void
    {
        $config = $this->settings->get(SettingKeyEnum::IMS, config('services.ims'));
        if (is_array($config)) {
            $this->setConfig($config);
        }
    }

    private function assertConfigured(): void
    {
        if (! $this->baseUrl || ! $this->apiKey) {
            throw new ExternalProvisioningException('IMS service configuration is missing.');
        }
    }
}
