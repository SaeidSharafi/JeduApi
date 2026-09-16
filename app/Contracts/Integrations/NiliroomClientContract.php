<?php

declare(strict_types=1);

namespace App\Contracts\Integrations;

use App\Models\User;
use Carbon\CarbonInterface;

interface NiliroomClientContract
{
    /**
     * Synchronize the user into Niliroom, enroll them in the room as a teacher, and
     * issue the single-use login grant that lands them on the room page.
     *
     * @return array{url: string, expires_at: CarbonInterface}
     */
    public function issueTeacherLoginGrant(User $user, string $roomId): array;

    public function isEnabled(): bool;

    public function assertConfigured(): void;

    public function isReady(): bool;
}
