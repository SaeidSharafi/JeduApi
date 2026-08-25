<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Data\Shop\Student\Blocks\LmsMoodleBlockData;
use App\Data\Shop\Student\Blocks\MoodleActivityData;
use App\Enums\System\SettingKeyEnum;
use App\Exceptions\Integrations\RecoverableProvisioningException;
use App\Exceptions\Integrations\UnrecoverableProvisioningException;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class MoodleService extends AbstractIntegrationService
{
    private string $baseUrl = '';

    private string $auth_userkey_token = '';

    private int $timeout = 30;

    /**
     * @return array{0:int,1:string}
     */
    public function findOrCreateUser(User $user): array
    {
        $email    = $user->email ?? sprintf('user-%d@jedu.ir', $user->phone);
        $username = $user->civil_id;
        if (! is_string($username) || $username === '') {
            throw new UnrecoverableProvisioningException(__('messages.integration.moodle.username_missing'));
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
            'users[0][firstname]' => $user->first_name ?: __('messages.integration.moodle.student_default'),
            'users[0][lastname]'  => $user->last_name ?: __('messages.integration.moodle.user_default'),
            'users[0][email]'     => $email,
            'users[0][password]'  => Str::password(16),
            'users[0][phone1]'    => $user->phone,
            'users[0][idnumber]'  => $user->civil_id,
        ]);

        if (! is_array($created) || ! isset($created[0]['id'])) {
            throw new UnrecoverableProvisioningException(__('messages.integration.moodle.user_creation_failed'));
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
        } catch (RecoverableProvisioningException $exception) {
            // If completion isn't enabled or configured, it's technically not "completed"
            if ($exception->getMoodleErrorCode() === 'nocriteriaset') {
                return false;
            }
            throw $exception;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getActivityCompletionStatus(int $moodleCourseId, int $moodleUserId): array
    {
        $params = [
            'courseid' => $moodleCourseId,
            'userid'   => $moodleUserId,
        ];
        try {
            $response = $this->call('core_completion_get_activities_completion_status', $params);
        } catch (RecoverableProvisioningException $exception) {
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
            throw new UnrecoverableProvisioningException(__('messages.integration.moodle.course_not_found'));
        }
        $response = reset($response);
        $modules  = [];
        foreach ($response['modules'] as $module) {
            if ($module['visible'] !== 1) {
                continue;
            }
            $modules[] = MoodleActivityData::from(
                [
                    'url'   => $this->baseUrl.'/mod/'.$module['modname'].'/view.php?id='.$module['cid'],
                    'cid'   => $module['id'],
                    'name'  => $module['name'],
                    'type'  => $module['modname'],
                    'state' => 0,
                ]
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

    /**
     * Get all visible quizzes that the user is enrolled in Moodle,
     * populated with completion states, quiz grades, and completion times.
     *
     *
     * @return array<int, LmsMoodleBlockData>
     */
    public function getAllQuizzes(int $moodleUserId): array
    {
        $courses = $this->call('core_enrol_get_users_courses', [
            'userid' => $moodleUserId,
        ]);

        if (! is_array($courses) || empty($courses)) {
            return [];
        }

        $params                   = [];
        $coursesData              = [];
        $courseCompletionStatuses = [];
        $courseGrades             = [];

        foreach ($courses as $course) {
            // Guard clause: skip invalid or invisible courses
            if (! is_array($course) || ! isset($course['id']) || ! $course['visible']) {
                continue;
            }

            $courseId              = (int) $course['id'];
            $params['courseids'][] = $courseId;

            // Determine if the entire course is completed
            $isCourseCompleted = false;
            try {
                $isCourseCompleted = $this->isCourseCompleted($courseId, $moodleUserId);
            } catch (UnrecoverableProvisioningException|RecoverableProvisioningException $e) {
                // Fall back to false
            }

            $coursesData[$courseId] = new LmsMoodleBlockData(
                visible: (bool) $course['visible'],
                name: $course['fullname'],
                course_url: $this->baseUrl.'/course/view.php?id='.$courseId,
                completed: $isCourseCompleted,
                activities: [],
            );

            // Fetch activity completion statuses for this course
            try {
                $courseCompletionStatuses[$courseId] = $this->getActivityCompletionStatus($courseId, $moodleUserId);
            } catch (UnrecoverableProvisioningException|RecoverableProvisioningException $e) {
                $courseCompletionStatuses[$courseId] = [];
            }

            // Fetch grades for this course
            try {
                $courseGrades[$courseId] = $this->getGrades($courseId, $moodleUserId);
            } catch (UnrecoverableProvisioningException|RecoverableProvisioningException $e) {
                $courseGrades[$courseId] = ['course_grade' => null, 'activities' => []];
            }
        }

        if (empty($params)) {
            return [];
        }

        $params['courseids'] = array_values(array_unique($params['courseids']));

        // Retrieve all quizzes matching those course IDs
        $response = $this->call('mod_quiz_get_quizzes_by_courses', $params);
        $quizzes  = data_get($response, 'quizzes', []);

        if (! is_array($quizzes) || empty($quizzes)) {
            return [];
        }

        // Filter only the visible quizzes
        $visibleQuizes = array_values(array_filter($quizzes, function ($quiz): bool {
            return is_array($quiz) && (bool) data_get($quiz, 'visible', true);
        }));

        foreach ($visibleQuizes as $quiz) {
            $courseId = (int) $quiz['course'];
            $cmid     = (int) $quiz['coursemodule']; // Course Module ID wrapper
            $course   = data_get($coursesData, $courseId);

            // Guard clause: skip if the parent course was not resolved or is invisible
            if (! $course) {
                continue;
            }

            // Determine completion state and timestamp of the specific quiz activity
            $state         = 0;
            $timeCompleted = null;

            if (isset($courseCompletionStatuses[$courseId][$cmid])) {
                $state         = (int) data_get($courseCompletionStatuses[$courseId][$cmid], 'state', 0);
                $timeCompleted = data_get($courseCompletionStatuses[$courseId][$cmid], 'timecompleted');
            }

            // Determine formatted grade of the specific quiz activity (if present)
            $grade = null;
            if (isset($courseGrades[$courseId]['activities'][$cmid])) {
                $grade = $courseGrades[$courseId]['activities'][$cmid];
            }

            // Append the quiz to the course's activity list
            $course->activities[] = MoodleActivityData::from(
                [
                    'url'           => $this->baseUrl.'/mod/quiz/view.php?id='.$cmid,
                    'cid'           => $cmid,
                    'name'          => $quiz['name'],
                    'type'          => 'quiz',
                    'state'         => $state,
                    'grade'         => $grade,
                    'timecompleted' => $timeCompleted,
                ]
            )->toArray();
        }
        $coursesData = array_filter($coursesData, function (LmsMoodleBlockData $course): bool {
            return ! empty($course->activities);
        });

        return array_values($coursesData);
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
            throw new UnrecoverableProvisioningException(__('messages.integration.moodle.auth_userkey_creation_failed'));
        }

        return $loginUrl;
    }

    /**
     * The Moodle role id assigned to students when enrolling them into a course.
     */
    public function getDefaultRoleId(): int
    {
        return (int) ($this->config['default_role_id'] ?? 5);
    }

    /**
     * The Moodle path students are sent to after login.
     */
    public function getLoginPath(): string
    {
        return (string) ($this->config['default_login_redirect_script'] ?? '/my/');
    }

    protected function getSettingKey(): SettingKeyEnum
    {
        return SettingKeyEnum::MOODLE;
    }

    protected function getConfigFallbackPath(): string
    {
        return 'services.moodle';
    }

    protected function validateConfig(): bool
    {
        return ! empty($this->config['base_url']) && ! empty($this->config['token']);
    }

    /**
     * @return array<mixed>|bool|string|int|float|null
     */
    /**
     * @param  array<string, mixed>  $params
     */
    private function call(string $function, array $params, ?string $token = null): mixed
    {
        $this->assertConfigured(); // throws UnrecoverableProvisioningException if not ready

        $response = $this->request($this->config['base_url'])->post('/webservice/rest/server.php', array_merge(
            [
                'wstoken'            => $token ?: $this->config['token'],
                'wsfunction'         => $function,
                'moodlewsrestformat' => 'json',
            ],
            $params,
        ));

        if ($response->failed()) {
            $status = $response->status();
            if ($status >= 500) {
                throw new RecoverableProvisioningException(__('messages.integration.moodle.server_error', ['function' => $function]), $status);
            }
            throw new UnrecoverableProvisioningException(__('messages.integration.moodle.request_failed', ['function' => $function]), $status);
        }

        $json = $response->json();
        if (is_array($json) && isset($json['exception'])) {
            $message = __('messages.integration.moodle.exception_response', ['message' => ((string) $json['message'] ?? 'Unknown error')]);
            // metaData['errorcode'] is what getMoodleErrorCode() reads — must be preserved
            throw new UnrecoverableProvisioningException($message, 0, null, $json);
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
