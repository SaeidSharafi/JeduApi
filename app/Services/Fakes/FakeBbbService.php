<?php

declare(strict_types=1);

namespace App\Services\Fakes;

use App\Contracts\Integrations\BbbClientContract;

/**
 * @codeCoverageIgnore
 */
final class FakeBbbService implements BbbClientContract
{
    /** @var array<string, mixed> */
    private array $config = [];

    /**
     * @param  array<string, mixed>  $config
     */
    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function assertConfigured(): void {}

    public function isReady(): bool
    {
        return true;
    }

    public function createMeeting(
        string $meetingId,
        string $name,
        ?string $attendeePw = null,
        ?string $moderatorPw = null
    ): void {
        // No-op in demo: meeting is assumed to already exist.
    }

    public function buildJoinUrl(string $meetingId, string $fullName, ?string $password = null): string
    {
        $baseUrl = (string) ($this->config['base_url'] ?? 'https://bbb.demo.jedushop.ir');
        $query   = http_build_query([
            'meetingID' => $meetingId,
            'fullName'  => $fullName,
            'password'  => $password ?? 'demo-attendee-pw',
        ]);

        return mb_rtrim($baseUrl, '/').'/bigbluebutton/api/join?'.$query.'&checksum=demo-checksum';
    }
}
