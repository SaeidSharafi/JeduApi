<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use AllowDynamicProperties;
use App\Enums\System\SettingKeyEnum;
use App\Exceptions\Integrations\ExternalProvisioningException;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

#[AllowDynamicProperties]
final class SpotPlayerService
{
    private string $apiKey;

    private string $endpoint;

    private bool $sandbox;

    public function __construct(private readonly SettingsService $settings)
    {
        $this->resolveConfig();
    }

    public function issueLicense(string $spotId, User $user): array
    {
        if ($this->endpoint === '' || $this->apiKey === '') {
            throw new ExternalProvisioningException('SpotPlayer service configuration is missing.');
        }

        $response = $this->request($this->endpoint, $this->apiKey)->post('', [
            'spot_id'       => $spotId,
            'mobile'        => $user->phone,
            'name'          => mb_trim(($user->first_name ?? '').' '.($user->last_name ?? '')),
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

    private function request(string $endpoint, string $apiKey): PendingRequest
    {
        return Http::baseUrl($endpoint)
            ->timeout((int) config('services.spotplayer.timeout', 15))
            ->acceptJson()
            ->withHeaders([
                'x-api-key' => $apiKey,
            ]);
    }

    private function resolveConfig(): void
    {
        $config         = $this->settings->get(SettingKeyEnum::SPOT_PLAYER, config('services.spotplayer'));
        $this->endpoint = (string) data_get($config, 'endpoint', '');
        $this->apiKey   = (string) data_get($config, 'api_key', '');
        $this->sandbox  = (bool) data_get($config, 'sandbox', false);

    }
}
