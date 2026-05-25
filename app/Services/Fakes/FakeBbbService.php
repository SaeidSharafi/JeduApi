<?php

declare(strict_types=1);

namespace App\Services\Fakes;

use App\Services\SettingsService;

final class FakeBbbService
{
    /** @var array<string, mixed> */
    private array $config = [];

    public function __construct(private readonly SettingsService $settings) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public function setConfig(array $config): void
    {
        $this->config = $config;
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

        return rtrim($baseUrl, '/').'/bigbluebutton/api/join?'.$query.'&checksum=demo-checksum';
    }
}
