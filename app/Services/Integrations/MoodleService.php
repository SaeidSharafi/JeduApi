<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Data\Shop\MyCourses\Blocks\LmsMoodleBlockData;
use App\Data\Shop\MyCourses\Blocks\MoodleActivityData;
use App\Enums\System\SettingKeyEnum;
use App\Exceptions\Integrations\ExternalProvisioningException;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class MoodleService
{
    private string $baseUrl = '';

    private string $token = '';

    private string $auth_userkey_token = '';

    private string $default_role_id = '';

    private string $default_login_redirect_script = '';

    private int $timeout = 30;

    public function __construct(private readonly SettingsService $settings)
    {
        $this->resolveConfig();
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function setConfig(array $config): void
    {
        $this->baseUrl                       = (string) ($config['base_url'] ?? data_get($config, 'baseUrl', ''));
        $this->token                         = (string) ($config['token'] ?? '');
        $this->auth_userkey_token            = (string) ($config['auth_userkey_token'] ?? '');
        $this->default_role_id               = (string) ($config['default_role_id'] ?? data_get($config, 'defaultRoleId', ''));
        $this->default_login_redirect_script = (string) ($config['default_login_redirect_script'] ?? data_get($config, 'defaultLoginRedirectScript', ''));
        $this->timeout                       = (int) ($config['timeout'] ?? 30);
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

    public function isCourseCompleted(int $moodleCourseId, int $moodleUserId): bool
    {
        $params = [
            'courseid' => $moodleCourseId,
            'userid'   => $moodleUserId,
        ];
        try {
            $response = $this->call('core_completion_get_course_completion_status', $params);

            return data_get($response, 'completionstatus.completed', false);
        } catch (ExternalProvisioningException $exception) {
            // If completion isn't enabled or configured, it's technically not "completed"
            if ($exception->getMoodleErrorCode() === 'nocriteriaset') {
                return false;
            }
            throw $exception;
        }
    }

    public function getActivityCompletionStatus(int $moodleCourseId, int $moodleUserId): array
    {
        $params = [
            'courseid' => $moodleCourseId,
            'userid'   => $moodleUserId,
        ];
        try {
            $response = $this->call('core_completion_get_activities_completion_status', $params);
        } catch (ExternalProvisioningException $exception) {
            // Return empty array if tracking isn't set up
            if ($exception->getMoodleErrorCode() === 'nocriteriaset') {
                return [];
            }
            throw $exception;
        }
        $completionStatuses         = data_get($response, 'statuses', []);
        $activityCompletionStatuses = [];
        foreach ($completionStatuses as $status) {
            $activityCompletionStatuses[$status['cmid']] = [
                'hascompletion' => $status['hascompletion'],
                'cmid'          => $status['cmid'],
                'state'         => $status['state'],
                'timecompleted' => $status['timecompleted'] ? Carbon::createFromTimestamp($status['timecompleted'])
                    ->toDateTimeString() : null,
            ];
        }

        return $activityCompletionStatuses;
    }

    /**
     * @return array{course_grade: string|null, activities: array<int, string>}
     */
    public function getGrades(int $moodleCourseId, int $moodleUserId): array
    {
        $params = [
            'courseid' => $moodleCourseId,
            'userid'   => $moodleUserId,
        ];

        $response   = $this->call('gradereport_user_get_grade_items', $params);
        $userGrades = data_get($response, 'usergrades.0.gradeitems', []);

        $result = [
            'course_grade' => null,
            'activities'   => [],
        ];

        foreach ($userGrades as $item) {
            // Course Total Grade
            if ($item['itemtype'] === 'course') {
                $result['course_grade'] = $item['gradeformatted'];
            } // Individual Activity Grade (Quiz, Assignment, etc.)
            elseif ($item['itemtype'] === 'mod' && ! empty($item['cmid'])) {
                $result['activities'][$item['cmid']] = $item['gradeformatted'];
            }
        }

        return $result;
    }

    public function getCourse(int $moodleCourseId): LmsMoodleBlockData
    {
        $params = [
            'courseid' => $moodleCourseId,
        ];

        $response = $this->call('core_course_get_contents', $params);
        if (! $response || ! is_array($response)) {
            throw new ExternalProvisioningException('Moodle course not found.');
        }
        $response = reset($response);
        $modules  = [];
        foreach ($response['modules'] as $module) {
            if ($module['visible'] !== 1) {
                continue;
            }
            $modules[] = new MoodleActivityData(
                url: $this->baseUrl.'/mod/'.$module['modname'].'/view.php?id='.$module['id'],
                cid: $module['id'],
                name: $module['name'],
                type: $module['modname'],
                state: 0
            );
        }

        return new LmsMoodleBlockData(
            visible: (bool) $response['visible'],
            name: $response['name'],
            course_url: $this->baseUrl.'/course/view.php?id='.$response['id'],
            completed: false,
            activities: $modules,
        );
    }

    public function enrollUser(
        int $moodleUserId,
        int $moodleCourseId,
        ?int $startTime = null,
        ?int $endTime = null,
        int $roleId = 5
    ): void {
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

    public function createUserKey(string $username, ?string $token = null): string
    {
        $result = $this->call('auth_userkey_request_login_url', [
            'user' => [
                'username' => $username,
            ],
        ], $token ?? $this->auth_userkey_token);

        $loginUrl = data_get($result, 'loginurl');
        if (! is_string($loginUrl) || $loginUrl === '') {
            throw new ExternalProvisioningException('Moodle auth_userkey creation failed.');
        }

        return $loginUrl;
    }

    private function resolveConfig(): void
    {
        $config = $this->settings->get(SettingKeyEnum::MOODLE, config('services.moodle'));
        if (is_array($config)) {
            $this->setConfig($config);
        }
    }

    /**
     * @return array<mixed>|bool|string|int|float|null
     */
    private function call(string $function, array $params, ?string $token = null): mixed
    {
        if ($this->baseUrl === '' || $this->token === '') {
            throw new ExternalProvisioningException('Moodle service configuration is missing.');
        }

        $response = $this->request($this->baseUrl)->post('/webservice/rest/server.php', array_merge(
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
            throw new ExternalProvisioningException(message: $message, metaData: $json);
        }

        return $json;
    }

    private function request(string $baseUrl): PendingRequest
    {
        return Http::baseUrl($baseUrl)
            ->timeout($this->timeout)
            ->asForm();
    }
}
