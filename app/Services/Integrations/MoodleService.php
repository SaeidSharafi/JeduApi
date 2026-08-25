<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Data\Shop\Student\Blocks\LmsMoodleBlockData;
use App\Data\Shop\Student\Blocks\MoodleActivityData;
use App\Data\Shop\Student\MoodleSsoUrlData;
use App\Enums\System\SettingKeyEnum;
use App\Exceptions\Integrations\RecoverableProvisioningException;
use App\Exceptions\Integrations\UnrecoverableProvisioningException;
use App\Models\Enrollment;
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
     * Get all visible quizzes that the user is enrolled in as a student,
     * populated with completion states, quiz grades, and completion times.
     *
     * @return array<int, LmsMoodleBlockData>
     */
    public function getAllQuizzes(int $moodleUserId): array
    {
        return $this->fetchUserQuizzes($moodleUserId, asTeacher: false);
    }

    /**
     * Get all quizzes for courses where the user has teacher/editing permissions.
     *
     * @return array<int, LmsMoodleBlockData>
     */
    public function getTeacherQuizzes(int $moodleUserId): array
    {
        return $this->fetchUserQuizzes($moodleUserId, asTeacher: true);
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
     * Generate SSO URL for a raw Moodle username.
     */
    public function generateSsoUrl(string $username, ?string $wantsUrl = null): ?MoodleSsoUrlData
    {
        try {
            $url = $this->createUserKey($username);

            return new MoodleSsoUrlData(url: $url, wantsurl: $wantsUrl);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }


    /**
     * Build the relative Moodle course URL from enrollment details.
     */
    public function getCourseWantsUrl(Enrollment $enrollment): ?string
    {
        $courseId = data_get($enrollment->productDeliveryOption?->details_json, 'moodle_course_id');

        return $courseId ? "/course/view.php?id={$courseId}" : null;
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
     * Unified pipeline to fetch and map quizzes for both students and teachers.
     *
     * @return array<int, LmsMoodleBlockData>
     */
    private function fetchUserQuizzes(int $moodleUserId, bool $asTeacher = false): array
    {
        $courses = $this->call('core_enrol_get_users_courses', [
            'userid' => $moodleUserId,
        ]);

        if (! is_array($courses) || empty($courses)) {
            return [];
        }
        // 1. Filter valid courses
        $courses = array_filter($courses, function ($course) use ($asTeacher): bool {
            return is_array($course) && isset($course['id']) && ($asTeacher || ! empty($course['visible']));
        });

        $courseIds = array_column($courses, 'id');

        // 2. If teacher mode, narrow down to courses where the user has editing rights
        if ($asTeacher) {
            $teacherCourseIds = $this->filterTeacherCourseIds($courseIds, $moodleUserId);
            $courses          = array_filter($courses, fn ($c) => in_array((int) $c['id'], $teacherCourseIds, true));
            $courseIds        = $teacherCourseIds;
        }

        if (empty($courseIds)) {
            return [];
        }

        // 3. For students only: load completion statuses & grades
        $studentContext = $asTeacher ? [] : $this->loadStudentCourseContext($courseIds, $moodleUserId);

        // 4. Initialize course block objects
        $coursesData = [];
        foreach ($courses as $course) {
            $courseId               = (int) $course['id'];
            $coursesData[$courseId] = new LmsMoodleBlockData(
                visible: (bool) $course['visible'],
                name: $course['fullname'],
                course_url: $this->baseUrl.'/course/view.php?id='.$courseId,
                completed: $studentContext['course_completed'][$courseId] ?? false,
                activities: [],
            );
        }

        // 5. Batch-fetch all quizzes for the targeted courses
        $response = $this->call('mod_quiz_get_quizzes_by_courses', [
            'courseids' => array_values(array_unique($courseIds)),
        ]);
        $quizzes = data_get($response, 'quizzes', []);

        if (! is_array($quizzes) || empty($quizzes)) {
            return [];
        }

        // 6. Map quizzes into their respective course blocks
        foreach ($quizzes as $quiz) {
            if (! is_array($quiz) || (! $asTeacher && ! (bool) data_get($quiz, 'visible', true))) {
                continue;
            }

            $courseId = (int) $quiz['course'];
            $cmid     = (int) $quiz['coursemodule'];

            if (! isset($coursesData[$courseId])) {
                continue;
            }

            $coursesData[$courseId]->activities[] = MoodleActivityData::from([
                'url'           => $this->baseUrl.'/mod/quiz/view.php?id='.$cmid,
                'cid'           => $cmid,
                'name'          => $quiz['name'],
                'type'          => 'quiz',
                'state'         => $studentContext['completion_statuses'][$courseId][$cmid]['state']         ?? 0,
                'grade'         => $studentContext['grades'][$courseId]['activities'][$cmid]                 ?? null,
                'timecompleted' => $studentContext['completion_statuses'][$courseId][$cmid]['timecompleted'] ?? null,
            ])->toArray();
        }

        // 7. Strip courses that have no matching quizzes
        return array_values(array_filter($coursesData, fn (LmsMoodleBlockData $c) => ! empty($c->activities)));
    }

    /**
     * Batch-fetch grades and completion states for student courses.
     *
     * @param  array<int>  $courseIds
     * @return array{course_completed: array<int, bool>, completion_statuses: array<int, array>, grades: array<int, array>}
     */
    private function loadStudentCourseContext(array $courseIds, int $moodleUserId): array
    {
        $context = [
            'course_completed'    => [],
            'completion_statuses' => [],
            'grades'              => [],
        ];

        foreach ($courseIds as $courseId) {
            try {
                $context['course_completed'][$courseId] = $this->isCourseCompleted($courseId, $moodleUserId);
            } catch (UnrecoverableProvisioningException|RecoverableProvisioningException) {
                $context['course_completed'][$courseId] = false;
            }

            try {
                $context['completion_statuses'][$courseId] = $this->getActivityCompletionStatus($courseId, $moodleUserId);
            } catch (UnrecoverableProvisioningException|RecoverableProvisioningException) {
                $context['completion_statuses'][$courseId] = [];
            }

            try {
                $context['grades'][$courseId] = $this->getGrades($courseId, $moodleUserId);
            } catch (UnrecoverableProvisioningException|RecoverableProvisioningException) {
                $context['grades'][$courseId] = ['course_grade' => null, 'activities' => []];
            }
        }

        return $context;
    }

    /**
     * Filter course IDs to only those where the user has editing/teacher permissions.
     *
     * @param  array<int>  $courseIds
     * @return array<int>
     */
    private function filterTeacherCourseIds(array $courseIds, int $moodleUserId): array
    {
        if (empty($courseIds)) {
            return [];
        }

        $coursecapabilities = [];
        foreach (array_unique($courseIds) as $courseId) {
            $coursecapabilities[] = [
                'courseid'     => $courseId,
                'capabilities' => [
                    'moodle/course:update', // Editing Teacher / Manager
                    'moodle/grade:viewall', // Non-editing Teacher
                ],
            ];
        }

        $response = $this->call('core_enrol_get_enrolled_users_with_capability', [
            'coursecapabilities' => $coursecapabilities,
        ]);

        if (! is_array($response)) {
            return [];
        }

        $teacherCourseIds = [];
        foreach ($response as $item) {
            $courseId = (int) data_get($item, 'courseid');
            $users    = data_get($item, 'users', []);

            foreach ($users as $user) {
                if ((int) data_get($user, 'id') === $moodleUserId) {
                    $teacherCourseIds[] = $courseId;
                    break;
                }
            }
        }

        return array_values(array_unique($teacherCourseIds));
    }

    /**
     * @return array<mixed>|bool|string|int|float|null
     */
    /**
     * @param  array<string, mixed>  $params
     */
    private function call(string $function, array $params, ?string $token = null): mixed
    {
        $this->assertConfigured();

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
