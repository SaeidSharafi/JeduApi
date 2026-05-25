<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Enums\System\SettingKeyEnum;
use App\Exceptions\Integrations\ExternalProvisioningException;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Http;

final class BbbService
{
    public function __construct(private readonly SettingsService $settings) {}

    public function createMeeting(
        string $meetingId,
        string $name,
        ?string $attendeePw = null,
        ?string $moderatorPw = null
    ): void {
        $config = $this->resolveConfig();

        $queryParams = [
            'meetingID'   => $meetingId,
            'name'        => $name,
            'attendeePW'  => $attendeePw ?: $config['default_attendee_pw'],
            'moderatorPW' => $moderatorPw ?: $config['default_moderator_pw'],
        ];

        $queryString = http_build_query($queryParams);

        $checksum = sha1('create'.$queryString.$config['secret']);

        $endpoint = sprintf('%s/%s/create?%s&checksum=%s',
            mb_rtrim($config['base_url'], '/'),
            mb_trim($config['api_path'], '/'),
            $queryString,
            $checksum
        );

        $response = Http::timeout($config['timeout'])->get($endpoint);

        if ($response->failed()) {
            throw new ExternalProvisioningException('BBB create meeting request failed.');
        }
    }

    /**
     * @codeCoverageIgnore
     */
    public function buildJoinUrl(string $meetingId, string $fullName, ?string $password = null): string
    {
        $config = $this->resolveConfig();

        $queryParams = [
            'meetingID' => $meetingId,
            'fullName'  => $fullName,
            'password'  => $password ?: $config['default_attendee_pw'],
        ];

        $queryString = http_build_query($queryParams);

        $checksum = sha1('join'.$queryString.$config['secret']);

        return sprintf('%s/%s/join?%s&checksum=%s',
            mb_rtrim($config['base_url'], '/'),
            mb_trim($config['api_path'], '/'),
            $queryString,
            $checksum
        );
    }

    /**
     * @return array{base_url: string, secret: string, api_path: string, default_attendee_pw: string, default_moderator_pw: string, timeout: int}
     */
    private function resolveConfig(): array
    {
        $config  = $this->settings->get(SettingKeyEnum::BIG_BLUE_BUTTON);
        $baseUrl = (string) data_get($config, 'base_url', '');
        $secret  = (string) data_get($config, 'secret', '');

        if ($baseUrl === '' || $secret === '') {
            throw new ExternalProvisioningException('BBB service configuration is missing.');
        }

        return [
            'base_url'            => $baseUrl,
            'secret'              => $secret,
            'api_path'            => (string) data_get($config, 'api_path', '/bigbluebutton/api'),
            'default_attendee_pw' => (string) data_get($config, 'default_attendee_pw',
                data_get($config, 'default_attendee_password', '')),
            'default_moderator_pw' => (string) data_get($config, 'default_moderator_pw',
                data_get($config, 'default_moderator_password', '')),
            'timeout' => (int) config('services.bbb.timeout', 30),
        ];
    }
}
