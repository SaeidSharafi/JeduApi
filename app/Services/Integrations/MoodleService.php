<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Exceptions\Integrations\ExternalProvisioningException;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class MoodleService
{
    private string $baseUrl;

    private string $token;

    private bool $configured = false;

    public function setConfig(array $config): void
    {
        $this->baseUrl    = $config['base_url'];
        $this->token      = $config['token'];
        $this->configured = true;
    }

    /**
     * @return array{0:int,1:string}
     */
    public function findOrCreateUser(User $user): array
    {
        $email    = $user->email ?? sprintf('user-%d@jedu.ir', $user->phone);
        $username = $user->civil_id;
        if (! is_string($username) || $username === '') {
            throw new ExternalProvisioningException('Moodle username source missing.');
        }

        $lookup = $this->call('core_user_get_users_by_field', [
            'field'     => 'username',
            'values[0]' => $username,
        ]);

        if (is_array($lookup) && isset($lookup[0]['id'])) {
            return [(int) $lookup[0]['id'], $username];
        }

        $created = $this->call('core_user_create_users', [
            'users[0][username]'  => $username,
            'users[0][firstname]' => $user->first_name ?: 'Student',
            'users[0][lastname]'  => $user->last_name ?: 'User',
            'users[0][email]'     => $email,
            'users[0][password]'  => Str::password(16),
            'users[0][phone1]'    => $user->phone,
            'users[0][idnumber]'  => $user->civil_id,
        ]);

        if (! is_array($created) || ! isset($created[0]['id'])) {
            throw new ExternalProvisioningException('Moodle user creation failed.');
        }

        return [(int) $created[0]['id'], $username];
    }

    public function enrollUser(int $moodleUserId, int $moodleCourseId, ?int $startTime = null, ?int $endTime = null, int $roleId = 5): void
    {
        $params = [
            'enrolments[0][roleid]'   => $roleId,
            'enrolments[0][userid]'   => $moodleUserId,
            'enrolments[0][courseid]' => $moodleCourseId,
        ];

        if ($startTime !== null) {
            $params['enrolments[0][timestart]'] = $startTime;
        }

        if ($endTime !== null) {
            $params['enrolments[0][timeend]'] = $endTime;
        }

        $this->call('enrol_manual_enrol_users', $params);
    }

    public function createUserKey(string $username, string $authUserkeyToken): string
    {
        $result = $this->call('auth_userkey_request_login_url', [
            'user' => [
                'username' => $username,
            ],
        ], $authUserkeyToken);

        $loginUrl = data_get($result, 'loginurl');
        if (! is_string($loginUrl) || $loginUrl === '') {
            throw new ExternalProvisioningException('Moodle auth_userkey creation failed.');
        }

        return $loginUrl;
    }

    /**
     * @return array<mixed>|bool|string|int|float|null
     */
    private function call(string $function, array $params, ?string $token = null): mixed
    {
        $this->assertConfigured();

        $response = $this->request()->post('/webservice/rest/server.php', array_merge(
            [
                'wstoken'            => $token ?: $this->token,
                'wsfunction'         => $function,
                'moodlewsrestformat' => 'json',
            ],
            $params,
        ));

        if ($response->failed()) {
            throw new ExternalProvisioningException(sprintf('Moodle request failed for %s.', $function));
        }

        $json = $response->json();
        if (is_array($json) && isset($json['exception'])) {
            $message = (string) ($json['message'] ?? 'Moodle returned an exception response.');
            throw new ExternalProvisioningException($message);
        }

        return $json;
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->timeout((int) config('services.moodle.timeout', 15))
            ->asForm();
    }

    private function assertConfigured(): void
    {
        if (! $this->configured) {
            throw new ExternalProvisioningException('Moodle service configuration is missing.');
        }
    }
}
