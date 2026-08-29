<?php

declare(strict_types=1);

namespace App\Services\Fakes;

use App\Contracts\Integrations\SkyroomClientContract;
use App\Models\User;

/**
 * @codeCoverageIgnore
 */
final class FakeSkyroomService implements SkyroomClientContract
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

    /** @return array{skyroom_user_id: int} */
    public function findOrCreateUser(User $user): array
    {
        return ['skyroom_user_id' => $this->stableId('user-'.$user->id)];
    }

    public function addUserToRoom(int $roomId, int $skyroomUserId): void {}

    public function createLoginUrl(
        int $roomId,
        string $userId,
        string $nickname,
        int $access = 1,
        int $ttl = 3600,
    ): string {
        return 'https://skyroom.demo.jedushop.ir/login?'.http_build_query([
            'room_id'  => $roomId,
            'user_id'  => $userId,
            'nickname' => $nickname,
            'access'   => $access,
            'ttl'      => $ttl,
        ]);
    }

    private function stableId(string $value): int
    {
        return (hexdec(mb_substr(hash('sha256', $value), 0, 8)) % 900000) + 100000;
    }
}
