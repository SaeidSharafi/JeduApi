<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Exceptions\Integrations\ExternalProvisioningException;
use Illuminate\Support\Facades\Http;

final class BbbService
{
    private string $baseUrl;

    private string $secret;

    private string $apiPath;

    private string $defaultAttendeePw;

    private string $defaultModeratorPw;

    private int $timeout;

    private bool $configured = false;

    public function setConfig(array $config): void
    {
        $this->baseUrl            = $config['base_url'];
        $this->secret             = $config['secret'];
        $this->apiPath            = $config['api_path'];
        $this->defaultAttendeePw  = $config['default_attendee_pw'];
        $this->defaultModeratorPw = $config['default_moderator_pw'];
        $this->timeout            = config('services.bbb.timeout', 30);
        $this->configured         = true;
    }

    public function createMeeting(
        string $meetingId,
        string $name,
        ?string $attendeePw = null,
        ?string $moderatorPw = null
    ): void {
        $this->assertConfigured();

        $queryParams = [
            'meetingID'   => $meetingId,
            'name'        => $name,
            'attendeePW'  => $attendeePw ?: $this->defaultAttendeePw,
            'moderatorPW' => $moderatorPw ?: $this->defaultModeratorPw,
        ];

        $queryString = http_build_query($queryParams);

        $checksum = sha1('create'.$queryString.$this->secret);

        $endpoint = sprintf('%s/%s/create?%s&checksum=%s',
            rtrim($this->baseUrl, '/'),
            trim($this->apiPath, '/'),
            $queryString,
            $checksum
        );

        $response = Http::timeout($this->timeout)->get($endpoint);

        if ($response->failed()) {
            throw new ExternalProvisioningException('BBB create meeting request failed.');
        }
    }

    /**
     * @codeCoverageIgnore
     */
    public function buildJoinUrl(string $meetingId, string $fullName, ?string $password = null): string
    {
        $this->assertConfigured();

        $queryParams = [
            'meetingID' => $meetingId,
            'fullName'  => $fullName,
            'password'  => $password ?: $this->defaultAttendeePw,
        ];

        $queryString = http_build_query($queryParams);

        $checksum = sha1('join'.$queryString.$this->secret);

        return sprintf('%s/%s/join?%s&checksum=%s',
            rtrim($this->baseUrl, '/'),
            trim($this->apiPath, '/'),
            $queryString,
            $checksum
        );
    }

    private function assertConfigured(): void
    {
        if (! $this->configured) {
            throw new ExternalProvisioningException('BBB service configuration is missing.');
        }
    }
}
