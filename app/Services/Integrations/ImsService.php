<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Enums\System\SettingKeyEnum;
use App\Models\User;
use Illuminate\Support\Facades\Http;

final class ImsService extends AbstractIntegrationService
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function storeStudent(array $payload): array  // fixed typo
    {
        $this->assertConfigured();

        $response = Http::baseUrl($this->config['base_url'])
            ->timeout((int) ($this->config['timeout'] ?? 15))
            ->acceptJson()
            ->withToken($this->config['api_key'])
            ->post('/api/v2/student', $payload);

        $this->handleHttpErrors($response, '/api/v2/student');

        return (array) ($response->json() ?? []);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function storeEnrolment(User $user, array $payload): array
    {
        $this->assertConfigured();

        $response = Http::baseUrl($this->config['base_url'])
            ->timeout((int) ($this->config['timeout'] ?? 15))
            ->acceptJson()
            ->withToken($this->config['api_key'])
            ->post('/api/v2/enrolment/', $payload);

        $this->handleHttpErrors($response, '/api/v2/enrolment');

        return (array) ($response->json() ?? []);
    }

    protected function getSettingKey(): SettingKeyEnum
    {
        return SettingKeyEnum::IMS;
    }

    protected function getConfigFallbackPath(): string
    {
        return 'services.ims';
    }

    protected function validateConfig(): bool
    {
        return ! empty($this->config['base_url']) && ! empty($this->config['api_key']);
    }
}
