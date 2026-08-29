<?php

declare(strict_types=1);

namespace App\Contracts\Integrations;

use App\Models\User;

interface SkyroomClientContract
{
    /** @return array<string, mixed> */
    public function findOrCreateUser(User $user): array;

    public function addUserToRoom(int $roomId, int $skyroomUserId): void;

    public function createLoginUrl(
        int $roomId,
        string $userId,
        string $nickname,
        int $access = 1,
        int $ttl = 3600,
    ): string;

    public function isEnabled(): bool;

    public function assertConfigured(): void;

    public function isReady(): bool;
}
