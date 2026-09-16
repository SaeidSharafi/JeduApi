<?php

declare(strict_types=1);

namespace App\Services\Fakes;

use App\Contracts\Integrations\NiliroomClientContract;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * @codeCoverageIgnore
 */
final class FakeNiliroomService implements NiliroomClientContract
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

    /** @return array{url: string, expires_at: CarbonImmutable} */
    public function issueTeacherLoginGrant(User $user, string $roomId): array
    {
        return [
            'url' => 'https://niliroom.demo.jedushop.ir/login?'.http_build_query([
                'user_id' => 'user-'.$user->id,
                'room_id' => $roomId,
            ]),
            'expires_at' => CarbonImmutable::now()->addMinutes(5),
        ];
    }

    public function issueStudentMeetingJoinGrant(User $user, string $roomId): string
    {
        return 'https://niliroom.demo.jedushop.ir/meetings/join?'.http_build_query([
            'user_id' => 'user-'.$user->id,
            'room_id' => $roomId,
        ]);
    }
}
