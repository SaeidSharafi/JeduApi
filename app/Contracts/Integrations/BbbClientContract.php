<?php

declare(strict_types=1);

namespace App\Contracts\Integrations;

interface BbbClientContract
{
    public function createMeeting(
        string $meetingId,
        string $name,
        ?string $attendeePw = null,
        ?string $moderatorPw = null,
    ): void;

    public function buildJoinUrl(string $meetingId, string $fullName, ?string $password = null): string;

    public function isEnabled(): bool;

    public function assertConfigured(): void;

    public function isReady(): bool;
}
