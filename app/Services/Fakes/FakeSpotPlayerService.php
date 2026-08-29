<?php

declare(strict_types=1);

namespace App\Services\Fakes;

use App\Contracts\Integrations\SpotPlayerClientContract;
use App\Models\User;

/**
 * @codeCoverageIgnore
 */
final class FakeSpotPlayerService implements SpotPlayerClientContract
{
    public function isEnabled(): bool
    {
        return true;
    }

    public function assertConfigured(): void {}

    public function isReady(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function issueLicense(string $spotId, User $user): array
    {
        $licenseKey = hash('sha256', $spotId.'|'.$user->uuid.'|demo-secret');

        return [
            'license_key' => '0001'.$licenseKey,
            'player_url'  => 'https://app.spotplayer.ir/player/'.$spotId.'/',
            'raw'         => [
                'status'      => 'ok',
                'license_id'  => $spotId,
                'license_key' => '0001'.$licenseKey,
                'player_url'  => 'https://app.spotplayer.ir/player/'.$spotId.'/',
                'created_at'  => now()->toIso8601String(),
            ],
        ];
    }
}
