<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Contracts\Integrations\SpotPlayerClientContract;
use App\Enums\System\SettingKeyEnum;
use App\Exceptions\Integrations\UnrecoverableProvisioningException;
use App\Models\User;
use Illuminate\Support\Facades\Http;

final class SpotPlayerService extends AbstractIntegrationService implements SpotPlayerClientContract
{
    /**
     * SpotPlayer returns a relative `url` for the issued licence; the documented
     * host that serves it is dl.spotplayer.ir.
     */
    private const PLAYER_BASE_URL = 'https://dl.spotplayer.ir';

    /**
     * @return array<string, mixed>
     */
    public function issueLicense(string $spotId, User $user): array
    {
        $this->assertConfigured();

        $response = Http::baseUrl($this->config['endpoint'])
            ->timeout((int) ($this->config['timeout'] ?? 15))
            ->acceptJson()
            ->withHeaders([
                '$API'   => (string) $this->config['api_key'],
                '$LEVEL' => '-1',
            ])
            ->post('', [
                'test'      => (bool) ($this->config['sandbox'] ?? false),
                'course'    => [$spotId],
                'name'      => mb_trim(($user->first_name ?? '').' '.($user->last_name ?? '')),
                'watermark' => [
                    'texts' => [['text' => (string) $user->phone]],
                ],
            ]);

        $data = $response->json();

        // SpotPlayer reports business failures (bad key, missing course, missing
        // watermark, …) with an `ex` envelope even on 5xx. Surface the upstream
        // message and treat it as final — replaying the same request cannot succeed.
        if (is_array($data) && isset($data['ex'])) {
            throw new UnrecoverableProvisioningException(
                (string) data_get($data, 'ex.msg', __('messages.integration.spot_player.service_error')),
                $response->status(),
                null,
                [
                    'http_status'      => $response->status(),
                    'endpoint'         => '/',
                    'raw_body_snippet' => $data,
                ],
            );
        }

        $this->handleHttpErrors($response, '/'); // handles 4xx/5xx without an ex envelope

        if (! is_array($data)) {
            throw new UnrecoverableProvisioningException(__('messages.integration.spot_player.invalid_response'));
        }

        $licenseKey = data_get($data, 'key');
        $playerPath = data_get($data, 'url');

        if (! is_string($licenseKey) || $licenseKey === '' || ! is_string($playerPath) || $playerPath === '') {
            throw new UnrecoverableProvisioningException(
                __('messages.integration.spot_player.invalid_response'),
                0,
                null,
                ['raw_body_snippet' => $data],
            );
        }

        return [
            'license_key' => $licenseKey,
            'player_url'  => self::PLAYER_BASE_URL.'/'.mb_ltrim($playerPath, '/'),
            'raw'         => $data,
        ];
    }

    protected function getSettingKey(): SettingKeyEnum
    {
        return SettingKeyEnum::SPOT_PLAYER;
    }

    protected function getConfigFallbackPath(): string
    {
        return 'provisioning.providers.spotplayer';
    }

    protected function validateConfig(): bool
    {
        return ! empty($this->config['endpoint']) && ! empty($this->config['api_key']);
    }
}
