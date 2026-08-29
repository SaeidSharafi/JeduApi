<?php

declare(strict_types=1);

namespace App\Contracts\Integrations;

use App\Models\User;

interface SpotPlayerClientContract
{
    /**
     * @return array{license_key: string|null, player_url: string|null, raw?: array<string, mixed>}
     */
    public function issueLicense(string $spotId, User $user): array;

    public function isEnabled(): bool;

    public function assertConfigured(): void;

    public function isReady(): bool;
}
