<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Exceptions\Integrations\ExternalProvisioningException;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

final class SpotPlayerService
{
    private string $endpoint;

    private string $apiKey;

    private bool $sandbox;

    private bool $configured;

    public function __construct(string $endpoint = '', string $apiKey = '', bool $sandbox = false)
    {
        $this->endpoint   = $endpoint;
        $this->apiKey     = $apiKey;
        $this->sandbox    = $sandbox;
        $this->configured = $endpoint !== '' && $apiKey !== '';
    }

    public function setConfig(array $config): void
    {
        $this->endpoint   = $config['endpoint'];
        $this->apiKey     = $config['api_key'];
        $this->sandbox    = (bool) ($config['sandbox'] ?? false);
        $this->configured = true;
    }

    public function issueLicense(string $spotId, User $user): array
    {
        $this->assertConfigured();

        $response = $this->request()->post('', [
            'spot_id'       => $spotId,
            'mobile'        => $user->phone,
            'name'          => trim(($user->first_name ?? '').' '.($user->last_name ?? '')),
            'email'         => $user->email,
            'national_code' => $user->civil_id,
            'sandbox'       => $this->sandbox,
        ]);

        if ($response->failed()) {
            throw new ExternalProvisioningException('SpotPlayer provisioning request failed.');
        }

        $data = $response->json();
        if (! is_array($data)) {
            throw new ExternalProvisioningException('SpotPlayer invalid response format.');
        }

        if ((isset($data['status']) && $data['status'] === false) || isset($data['error'])) {
            $message = (string) ($data['message'] ?? $data['error'] ?? 'SpotPlayer returned an error response.');
            throw new ExternalProvisioningException($message);
        }

        return [
            'license_key' => data_get($data, 'license_key') ?? data_get($data, 'data.license_key'),
            'player_url'  => data_get($data, 'player_url')  ?? data_get($data, 'data.player_url'),
            'raw'         => $data,
        ];
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl($this->endpoint)
            ->timeout((int) config('services.spotplayer.timeout', 15))
            ->acceptJson()
            ->withHeaders([
                'x-api-key' => $this->apiKey,
            ]);
    }

    private function assertConfigured(): void
    {
        if (! $this->configured) {
            throw new ExternalProvisioningException('SpotPlayer service configuration is missing.');
        }
    }
}
