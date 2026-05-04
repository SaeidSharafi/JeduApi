<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Exceptions\Integrations\ExternalProvisioningException;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final readonly class MoodleService
{
    public function findOrCreateUser(User $user): int
    {
        $email    = $user->email ?? sprintf('user-%d@jedushop.local', $user->id);
        $username = $user->phone ?: $email;

        $lookup = $this->call('core_user_get_users_by_field', [
            'field'     => 'email',
            'values[0]' => $email,
        ]);

        if (is_array($lookup) && isset($lookup[0]['id'])) {
            return (int) $lookup[0]['id'];
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

        return (int) $created[0]['id'];
    }

    public function enrollUser(int $moodleUserId, int $moodleCourseId, ?int $startTime = null, ?int $endTime = null): void
    {
        $params = [
            'enrolments[0][roleid]'   => (int) config('services.moodle.default_role_id', 5),
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

    public function createUserKey(int $moodleUserId): string
    {
        $result = $this->call('auth_userkey_create_user_key', [
            'userid' => $moodleUserId,
        ], (string) config('services.moodle.auth_userkey_token'));

        $key = data_get($result, 'key');
        if (! is_string($key) || $key === '') {
            throw new ExternalProvisioningException('Moodle auth_userkey creation failed.');
        }

        return $key;
    }

    /**
     * @return array<mixed>|bool|string|int|float|null
     */
    private function call(string $function, array $params, ?string $token = null): mixed
    {
        $response = $this->request()->post('/webservice/rest/server.php', array_merge(
            [
                'wstoken'            => $token ?: (string) config('services.moodle.token'),
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
        return Http::baseUrl((string) config('services.moodle.base_url'))
            ->timeout((int) config('services.moodle.timeout', 15))
            ->asForm();
    }
}
