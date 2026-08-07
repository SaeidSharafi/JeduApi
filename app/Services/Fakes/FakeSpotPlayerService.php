<?php

declare(strict_types=1);

namespace App\Services\Fakes;

use App\Models\User;
use App\Services\SettingsService;

/**
 * @codeCoverageIgnore
 */
final class FakeSpotPlayerService
{
    public function __construct(private readonly SettingsService $settings) {}

    /**
     * @return array<string, mixed>
     */
    public function issueLicense(string $spotId, User $user): array
    {
        $licenseKey = hash('sha256', $spotId.$user->id.'demo-secret');

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
