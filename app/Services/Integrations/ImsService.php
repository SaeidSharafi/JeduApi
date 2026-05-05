<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Exceptions\Integrations\ExternalProvisioningException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

final class ImsService
{
    private string $baseUrl;

    private string $apiKey;

    private bool $configured = false;

    public function setConfig(array $config): void
    {
        $this->baseUrl    = $config['base_url'];
        $this->apiKey     = $config['api_key'];
        $this->configured = true;
    }

    public function provisionEnrollment(array $payload): array
    {
        $this->assertConfigured();

        $response = Http::baseUrl($this->baseUrl)
            ->timeout(15)
            ->acceptJson()
            ->withHeaders(['X-API-KEY' => $this->apiKey])
            ->post('/api/v1/enrol', $payload);

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

    private function assertConfigured(): void
    {
        if (! $this->configured) {
            throw new ExternalProvisioningException('IMS service configuration is missing.');
        }
    }
}
