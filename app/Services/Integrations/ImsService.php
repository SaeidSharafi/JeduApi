<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Enums\System\SettingKeyEnum;
use App\Exceptions\Integrations\ExternalProvisioningException;
use App\Services\SettingsService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

final class ImsService
{
    private string $baseUrl = '';

    private string $apiKey = '';

    private string $apiKeyHeader = 'X-API-KEY';

    private string $enrollmentsEndpoint = '/api/v1/enrol';

    private int $timeout = 30;

    private bool $configured = false;

    public function __construct(private readonly SettingsService $settings) {
        $this->resolveConfig();
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function setConfig(array $config): void
    {
        $this->baseUrl             = (string) ($config['base_url'] ?? '');
        $this->apiKey              = (string) ($config['api_key'] ?? '');
        $this->apiKeyHeader        = (string) ($config['api_key_header'] ?? 'X-API-KEY');
        $this->enrollmentsEndpoint = (string) ($config['enrollments_endpoint'] ?? '/api/v1/enrol');
        $this->timeout             = (int) ($config['timeout'] ?? 30);
        $this->configured          = $this->baseUrl !== '' && $this->apiKey !== '';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function provisionEnrollment(array $payload): array
    {
        $this->assertConfigured();

        $response = Http::baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->acceptJson()
            ->withHeader($this->apiKeyHeader, $this->apiKey)
            ->post($this->enrollmentsEndpoint, $payload);

        if ($response->failed()) {
            throw new ExternalProvisioningException('IMS provisioning request failed.');
        }

        $responseData = $response->json();
        $status       = data_get($responseData, 'status');
        $message      = data_get($responseData, 'message');

        if ($status !== true || $message !== 'ok') {
            $errors       = Arr::wrap(data_get($responseData, 'errors', []));
            $errorMessage = ! empty($errors)
                ? implode('; ', array_values($errors))
                : 'IMS provisioning response was not successful.';

            throw new ExternalProvisioningException($errorMessage);
        }

        return is_array($responseData) ? $responseData : [];
    }
    private function resolveConfig(): void
    {
        $config = $this->settings->get(SettingKeyEnum::MOODLE, config('services.ims'));
        if (is_array($config)) {
            $this->setConfig($config);
        }
    }

    private function assertConfigured(): void
    {
        if (! $this->configured) {
            throw new ExternalProvisioningException('IMS service configuration is missing.');
        }
    }
}
