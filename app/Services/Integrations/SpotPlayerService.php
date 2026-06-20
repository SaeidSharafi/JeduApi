<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Enums\System\SettingKeyEnum;
use App\Exceptions\Integrations\UnrecoverableProvisioningException;
use App\Models\User;
use Illuminate\Support\Facades\Http;

final class SpotPlayerService extends AbstractIntegrationService
{
    public function issueLicense(string $spotId, User $user): array
    {
        $this->assertConfigured();

        $response = Http::baseUrl($this->config['endpoint'])
            ->timeout((int) config('services.spotplayer.timeout', 15))
            ->acceptJson()
            ->withHeaders(['x-api-key' => $this->config['api_key']])
            ->post('', [
                'spot_id'       => $spotId,
                'mobile'        => $user->phone,
                'name'          => mb_trim(($user->first_name ?? '').' '.($user->last_name ?? '')),
                'email'         => $user->email,
                'national_code' => $user->civil_id,
                'sandbox'       => (bool) ($this->config['sandbox'] ?? false),
            ]);

        $this->handleHttpErrors($response, '/'); // handles 4xx/5xx

        $data = $response->json();
        if (! is_array($data)) {
            throw new UnrecoverableProvisioningException('SpotPlayer returned an invalid response format.');
        }

        // Application-level error (bad spot_id, etc.) — retrying won't fix it
        if ((isset($data['status']) && $data['status'] === false) || isset($data['error'])) {
            $message = (string) ($data['message'] ?? $data['error'] ?? 'SpotPlayer returned an error.');
            throw new UnrecoverableProvisioningException($message, 0, null, ['raw_response' => $data]);
        }

        return [
            'license_key' => data_get($data, 'license_key') ?? data_get($data, 'data.license_key'),
            'player_url'  => data_get($data, 'player_url')  ?? data_get($data, 'data.player_url'),
            'raw'         => $data,
        ];
    }

    protected function getSettingKey(): SettingKeyEnum
    {
        return SettingKeyEnum::SPOT_PLAYER;
    }

    protected function getConfigFallbackPath(): string
    {
        return 'services.spotplayer';
    }

    protected function validateConfig(): bool
    {
        return ! empty($this->config['endpoint']) && ! empty($this->config['api_key']);
    }
}
