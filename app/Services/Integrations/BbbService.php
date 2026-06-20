<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Enums\System\SettingKeyEnum;
use App\Exceptions\Integrations\RecoverableProvisioningException;

final class BbbService extends AbstractIntegrationService
{
    public function createMeeting(
        string $meetingId,
        string $name,
        ?string $attendeePw = null,
        ?string $moderatorPw = null,
    ): void {
        $queryParams = [
            'meetingID'   => $meetingId,
            'name'        => $name,
            'attendeePW'  => $attendeePw ?: ($this->config['default_attendee_pw'] ?? ''),
            'moderatorPW' => $moderatorPw ?: ($this->config['default_moderator_pw'] ?? ''),
        ];

        $queryString = http_build_query($queryParams);
        $checksum    = sha1('create'.$queryString.$this->config['secret']);
        $endpoint    = $this->buildEndpoint('create', $queryString, $checksum);

        $response = \Illuminate\Support\Facades\Http::timeout($this->config['timeout'] ?? 30)
            ->get($endpoint);

        if ($response->failed()) {
            // BBB failures are typically transient (server restart, network) — recoverable.
            throw new RecoverableProvisioningException(
                'BBB create meeting request failed.',
                $response->status(),
            );
        }
    }

    /** @codeCoverageIgnore */
    public function buildJoinUrl(string $meetingId, string $fullName, ?string $password = null): string
    {
        $queryParams = [
            'meetingID' => $meetingId,
            'fullName'  => $fullName,
            'password'  => $password ?: ($this->config['default_attendee_pw'] ?? ''),
        ];

        $queryString = http_build_query($queryParams);
        $checksum    = sha1('join'.$queryString.$this->config['secret']);

        return $this->buildEndpoint('join', $queryString, $checksum);
    }

    protected function getSettingKey(): SettingKeyEnum
    {
        return SettingKeyEnum::BIG_BLUE_BUTTON;
    }

    protected function getConfigFallbackPath(): string
    {
        return 'services.bbb';
    }

    protected function validateConfig(): bool
    {
        return ! empty($this->config['base_url']) && ! empty($this->config['secret']);
    }

    private function buildEndpoint(string $action, string $queryString, string $checksum): string
    {
        return sprintf(
            '%s/%s/%s?%s&checksum=%s',
            mb_rtrim($this->config['base_url'], '/'),
            mb_trim($this->config['api_path'] ?? '/bigbluebutton/api', '/'),
            $action,
            $queryString,
            $checksum,
        );
    }
}
