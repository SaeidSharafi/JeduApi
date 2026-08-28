<?php

declare(strict_types=1);

use App\Enums\MoodleActivityStateEnum;
use App\Enums\System\SettingKeyEnum;
use App\Exceptions\Integrations\RecoverableProvisioningException;
use App\Exceptions\Integrations\UnrecoverableProvisioningException;
use App\Models\User;
use App\Services\Fakes\FakeMoodleService;
use App\Services\Integrations\MoodleService;
use App\Services\SettingsService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();

    $settings = $this->mock(SettingsService::class);
    $settings->shouldReceive('get')
        ->with(SettingKeyEnum::MOODLE, Mockery::any())
        ->andReturn([
            'base_url'           => 'https://moodle.test',
            'token'              => 'moodle-token',
            'auth_userkey_token' => 'AUTH_USER_KEY',
        ]);

    $this->moodleService = app(MoodleService::class);
});

it('returns existing moodle user id when found by email', function (): void {
    $user = User::factory()->create([
        'email' => 'student@example.test',
    ]);

    Http::fake([
        'https://moodle.test/*' => Http::response([
            ['id' => 11],
        ], 200),
    ]);

    [$id] = $this->moodleService->findOrCreateUser($user);

    expect($id)->toBe(11);

    Http::assertSentCount(1);
});

it('creates moodle user when lookup is empty', function (): void {
    $user = User::factory()->create([
        'email' => 'student2@example.test',
    ]);

    Http::fake([
        'https://moodle.test/*' => Http::sequence()
            ->push([], 200)
            ->push([
                ['id' => 22, 'username' => 'student2'],
            ], 200),
    ]);

    [$id, $username] = $this->moodleService->findOrCreateUser($user);

    expect($id)->toBe(22);

    Http::assertSentCount(2);
});

it('throws when username does not exist on user model', function (): void {
    $user = User::factory()->create([
        'civil_id' => null,
    ]);

    expect(fn () => $this->moodleService->findOrCreateUser($user))
        ->toThrow(UnrecoverableProvisioningException::class, 'Moodle username source missing.');
});

it('throws when moodle user creation response missing id', function (): void {
    $user = User::factory()->create();

    Http::fake([
        'https://moodle.test/*' => Http::sequence()
            ->push([], 200)
            ->push([], 200),
    ]);

    expect(fn () => $this->moodleService->findOrCreateUser($user))
        ->toThrow(UnrecoverableProvisioningException::class, 'Moodle user creation failed.');
});

it('enrolls user with optional start and end times', function (): void {
    Http::fake([
        'https://moodle.test/*' => Http::response([], 200),
    ]);

    $this->moodleService->enrollUser(55, 101, 1700000000, 1700003600);

    Http::assertSent(function (Request $request): bool {
        $payload = $request->data();

        return $request->url()                      === 'https://moodle.test/webservice/rest/server.php'
            && $payload['wsfunction']               === 'enrol_manual_enrol_users'
            && $payload['enrolments[0][userid]']    === 55
            && $payload['enrolments[0][courseid]']  === 101
            && $payload['enrolments[0][timestart]'] === 1700000000
            && $payload['enrolments[0][timeend]']   === 1700003600;
    });
});

it('creates moodle user key', function (): void {
    Http::fake([
        'https://moodle.test/*' => Http::response([
            'loginurl' => 'https://moodle.test?key=testkey',
        ], 200),
    ]);

    $url = $this->moodleService->createUserKey('1122334', 'AUTH_USER_KEY');

    expect($url)->toBe('https://moodle.test?key=testkey');

    Http::assertSent(function (Request $request): bool {
        return $request->data()['wstoken']          === 'AUTH_USER_KEY'
            && $request->data()['wsfunction']       === 'auth_userkey_request_login_url'
            && $request->data()['user']['username'] === '1122334';
    });
});

it('throws when user key missing from moodle response', function (): void {
    Http::fake([
        'https://moodle.test/*' => Http::response([], 200),
    ]);

    expect(fn () => $this->moodleService->createUserKey('1122334', 'AUTH_USER_KEY'))
        ->toThrow(UnrecoverableProvisioningException::class, 'Moodle auth_userkey creation failed.');
});

it('throws when moodle request returns failed status', function (): void {
    Http::fake([
        'https://moodle.test/*' => Http::response([], 500),
    ]);

    expect(fn () => $this->moodleService->enrollUser(1, 2))
        ->toThrow(RecoverableProvisioningException::class, 'Moodle server error for enrol_manual_enrol_users.');
});

it('throws when response contains exception', function (): void {
    Http::fake([
        'https://moodle.test/*' => Http::response([
            'exception' => 'Exception',
            'message'   => 'Something went wrong',
        ], 200),
    ]);

    expect(fn () => $this->moodleService->createUserKey('1122334', 'AUTH_USER_KEY'))
        ->toThrow(UnrecoverableProvisioningException::class);
});

it('throws when service used before configuration', function (): void {
    $settings = $this->mock(SettingsService::class);
    $settings->shouldReceive('get')
        ->with(SettingKeyEnum::MOODLE, Mockery::any())
        ->andReturn(['base_url' => '', 'token' => '']);

    $service = app(MoodleService::class);

    expect(fn () => $service->enrollUser(1, 2))
        ->toThrow(UnrecoverableProvisioningException::class);
});

// ─── isCourseCompleted ─────────────────────────────────────────────

it('returns true when moodle course is completed', function (): void {
    Http::fake([
        'https://moodle.test/*' => Http::response([
            'completionstatus' => [
                'completed' => true,
            ],
        ], 200),
    ]);

    $result = $this->moodleService->isCourseCompleted(101, 55);

    expect($result)->toBeTrue();

    Http::assertSent(fn (Request $r): bool => $r->data()['wsfunction'] === 'core_completion_get_course_completion_status');
});

it('returns false when moodle course is not completed', function (): void {
    Http::fake([
        'https://moodle.test/*' => Http::response([
            'completionstatus' => [
                'completed' => false,
            ],
        ], 200),
    ]);

    $result = $this->moodleService->isCourseCompleted(101, 55);

    expect($result)->toBeFalse();
});

it('re-throws non-nocriteriaset RecoverableProvisioningException from isCourseCompleted', function (): void {
    Http::fake([
        'https://moodle.test/*' => Http::response([], 500),
    ]);

    expect(fn () => $this->moodleService->isCourseCompleted(101, 55))
        ->toThrow(RecoverableProvisioningException::class, 'Moodle server error');
});

// ─── getActivityCompletionStatus ───────────────────────────────────

it('returns activity completion statuses for a course', function (): void {
    Http::fake([
        'https://moodle.test/*' => Http::response([
            'statuses' => [
                [
                    'cmid'          => 10,
                    'hascompletion' => true,
                    'state'         => 1,
                    'timecompleted' => 1700000000,
                ],
                [
                    'cmid'          => 11,
                    'hascompletion' => false,
                    'state'         => 0,
                    'timecompleted' => null,
                ],
            ],
        ], 200),
    ]);

    $result = $this->moodleService->getActivityCompletionStatus(101, 55);

    expect($result)->toHaveKeys([10, 11]);
    expect($result[10]['state'])->toBe(1);
    expect($result[10]['timecompleted'])->toBe('2023-11-14 22:13:20');
    expect($result[11]['timecompleted'])->toBeNull();

    Http::assertSent(fn ($r): bool => $r['wsfunction'] === 'core_completion_get_activities_completion_status');
});

it('re-throws non-nocriteriaset exception from getActivityCompletionStatus on 500', function (): void {
    Http::fake([
        'https://moodle.test/*' => Http::response([], 500),
    ]);

    expect(fn () => $this->moodleService->getActivityCompletionStatus(101, 55))
        ->toThrow(RecoverableProvisioningException::class, 'Moodle server error');
});

// ─── getGrades ─────────────────────────────────────────────────────

it('returns course_grade and activity grades', function (): void {
    Http::fake([
        'https://moodle.test/*' => Http::response([
            'usergrades' => [
                [
                    'gradeitems' => [
                        ['itemtype' => 'course', 'gradeformatted' => '85.50'],
                        ['itemtype' => 'mod', 'cmid' => 10, 'gradeformatted' => '90.00'],
                        ['itemtype' => 'mod', 'cmid' => 11, 'gradeformatted' => '75.00'],
                    ],
                ],
            ],
        ], 200),
    ]);

    $result = $this->moodleService->getGrades(101, 55);

    expect($result['course_grade'])->toBe('85.50');
    expect($result['activities'])->toHaveKeys([10, 11]);
    expect($result['activities'][10])->toBe('90.00');
    expect($result['activities'][11])->toBe('75.00');

    Http::assertSent(fn ($r): bool => $r['wsfunction'] === 'gradereport_user_get_grade_items');
});

it('returns empty course_grade and activities when no graded items', function (): void {
    Http::fake([
        'https://moodle.test/*' => Http::response([
            'usergrades' => [
                [
                    'gradeitems' => [],
                ],
            ],
        ], 200),
    ]);

    $result = $this->moodleService->getGrades(101, 55);

    expect($result['course_grade'])->toBeNull();
    expect($result['activities'])->toBe([]);
});

// ─── getCourse ─────────────────────────────────────────────────────

it('returns LmsMoodleBlockData with visible modules', function (): void {
    Http::fake([
        'https://moodle.test/*' => Http::response([
            [
                'id'      => 101,
                'name'    => 'Test Course',
                'visible' => 1,
                'modules' => [
                    ['id' => 1, 'cid' => 10, 'name' => 'Lesson 1', 'modname' => 'resource', 'visible' => 1],
                    ['id' => 2, 'cid' => 11, 'name' => 'Hidden', 'modname' => 'quiz', 'visible' => 0],
                    ['id' => 3, 'cid' => 12, 'name' => 'Forum', 'modname' => 'forum', 'visible' => 1],
                ],
            ],
        ], 200),
    ]);

    $result = $this->moodleService->getCourse(101);

    expect($result->name)->toBe('Test Course');
    expect($result->visible)->toBeTrue();
    expect($result->completed)->toBeFalse();
    expect($result->activities)->toHaveCount(2);
    expect($result->activities[0]->cid)->toBe(1);
    expect($result->activities[0]->name)->toBe('Lesson 1');
    expect($result->activities[1]->cid)->toBe(3);
    expect($result->activities[1]->name)->toBe('Forum');

    Http::assertSent(fn ($r): bool => $r['wsfunction'] === 'core_course_get_contents');
});

it('builds fake Moodle activities with enum-backed completion states', function (): void {
    $course = app(FakeMoodleService::class)->getCourse(101);

    expect($course->activities)->toHaveCount(3)
        ->and($course->activities[0]->state)->toBe(MoodleActivityStateEnum::INCOMPLETE);
});

it('throws when moodle course is not found', function (): void {
    Http::fake([
        'https://moodle.test/*' => Http::response([], 200),
    ]);

    expect(fn () => $this->moodleService->getCourse(999))
        ->toThrow(UnrecoverableProvisioningException::class, 'Moodle course not found.');
});

// ─── getAllQuizzes ─────────────────────────────────────────────────

it('returns empty array when user has no enrolled moodle courses', function (): void {
    Http::fake([
        'https://moodle.test/*' => Http::response([], 200),
    ]);

    $result = $this->moodleService->getAllQuizzes(55);

    expect($result)->toBe([]);

    Http::assertSent(fn ($r): bool => $r['wsfunction'] === 'core_enrol_get_users_courses');
});

it('returns empty array when no quizzes in enrolled courses', function (): void {
    $sequence = Http::sequence();

    // 1. core_enrol_get_users_courses — 1 course
    $sequence->push([
        ['id' => 101, 'visible' => 1, 'fullname' => 'Course A'],
    ], 200);

    // 2. core_completion_get_course_completion_status — completed=true
    $sequence->push(['completionstatus' => ['completed' => true]], 200);

    // 3. core_completion_get_activities_completion_status — empty
    $sequence->push([], 200);

    // 4. gradereport_user_get_grade_items — empty
    $sequence->push(['usergrades' => [[]]], 200);

    // 5. mod_quiz_get_quizzes_by_courses — no quizzes
    $sequence->push(['quizzes' => []], 200);

    Http::fake(['https://moodle.test/*' => $sequence]);

    $result = $this->moodleService->getAllQuizzes(55);

    expect($result)->toBe([]);
});

it('returns quizzes with completion and grade data', function (): void {
    $sequence = Http::sequence();

    // 1. core_enrol_get_users_courses — 1 visible course
    $sequence->push([
        ['id' => 101, 'visible' => 1, 'fullname' => 'Course A'],
    ], 200);

    // 2. core_completion_get_course_completion_status
    $sequence->push(['completionstatus' => ['completed' => true]], 200);

    // 3. core_completion_get_activities_completion_status — quiz cmid=10 completed
    $sequence->push([
        'statuses' => [
            ['cmid' => 10, 'hascompletion' => true, 'state' => 2, 'timecompleted' => 1700000000],
        ],
    ], 200);

    // 4. gradereport_user_get_grade_items — quiz cmid=10 has grade
    $sequence->push([
        'usergrades' => [[
            'gradeitems' => [
                ['itemtype' => 'course', 'gradeformatted' => '92.00'],
                ['itemtype' => 'mod', 'cmid' => 10, 'gradeformatted' => '88.00'],
            ],
        ]],
    ], 200);

    // 5. mod_quiz_get_quizzes_by_courses — 1 quiz matching cmid=10
    $sequence->push([
        'quizzes' => [
            ['id' => 1, 'course' => 101, 'coursemodule' => 10, 'name' => 'Quiz 1', 'visible' => 1],
        ],
    ], 200);

    Http::fake(['https://moodle.test/*' => $sequence]);

    $result = $this->moodleService->getAllQuizzes(55);

    expect($result)->toHaveCount(1);
    expect($result[0]->name)->toBe('Course A');
    expect($result[0]->completed)->toBeTrue();
    expect($result[0]->course_grade)->toBeNull();
    expect($result[0]->activities)->toHaveCount(1);
    expect($result[0]->activities[0]['cid'])->toBe(10);
    expect($result[0]->activities[0]['name'])->toBe('Quiz 1');
    expect($result[0]->activities[0]['grade'])->toBe('88.00');
    expect($result[0]->activities[0]['state']['value'])->toBe(2);
});

it('skips invisible courses in getAllQuizzes', function (): void {
    $sequence = Http::sequence();

    // 1. core_enrol_get_users_courses — 1 invisible course
    $sequence->push([
        ['id' => 101, 'visible' => 0, 'fullname' => 'Hidden Course'],
    ], 200);

    Http::fake(['https://moodle.test/*' => $sequence]);

    $result = $this->moodleService->getAllQuizzes(55);

    expect($result)->toBe([]);
});

it('skips courses not in coursesData in getAllQuizzes', function (): void {
    $sequence = Http::sequence();

    // 1. core_enrol_get_users_courses — 1 course
    $sequence->push([
        ['id' => 101, 'visible' => 1, 'fullname' => 'Course A'],
    ], 200);

    // 2. core_completion_get_course_completion_status
    $sequence->push(['completionstatus' => ['completed' => false]], 200);

    // 3. core_completion_get_activities_completion_status
    $sequence->push(['statuses' => []], 200);

    // 4. gradereport_user_get_grade_items
    $sequence->push(['usergrades' => [[]]], 200);

    // 5. mod_quiz_get_quizzes_by_courses — quiz referencing non-existent course
    $sequence->push([
        'quizzes' => [
            ['id' => 1, 'course' => 999, 'coursemodule' => 99, 'name' => 'Orphan Quiz', 'visible' => 1],
        ],
    ], 200);

    Http::fake(['https://moodle.test/*' => $sequence]);

    $result = $this->moodleService->getAllQuizzes(55);

    expect($result)->toBe([]);
});

it('handles exceptions gracefully in getAllQuizzes inner loops', function (): void {
    $sequence = Http::sequence();

    // 1. courses
    $sequence->push([
        ['id' => 101, 'visible' => 1, 'fullname' => 'Course A'],
    ], 200);

    // 2. isCourseCompleted throws
    $sequence->push(['exception' => 'moodle_exception', 'errorcode' => 'other', 'message' => 'Fail'], 200);

    // 3. getActivityCompletionStatus throws
    $sequence->push(['exception' => 'moodle_exception', 'errorcode' => 'other', 'message' => 'Fail'], 200);

    // 4. getGrades throws
    $sequence->push(['exception' => 'moodle_exception', 'errorcode' => 'other', 'message' => 'Fail'], 200);

    // 5. mod_quiz_get_quizzes_by_courses — no quizzes
    $sequence->push(['quizzes' => []], 200);

    Http::fake(['https://moodle.test/*' => $sequence]);

    $result = $this->moodleService->getAllQuizzes(55);

    expect($result)->toBe([]);
});

// ─── call() error handling ─────────────────────────────────────────

it('throws UnrecoverableProvisioningException on 4xx response', function (): void {
    Http::fake([
        'https://moodle.test/*' => Http::response([], 400),
    ]);

    expect(fn () => $this->moodleService->enrollUser(1, 2))
        ->toThrow(UnrecoverableProvisioningException::class, 'Moodle request failed for enrol_manual_enrol_users.');
});

it('throws with errorcode metadata when moodle returns exception response', function (): void {
    Http::fake([
        'https://moodle.test/*' => Http::response([
            'exception' => 'moodle_exception',
            'errorcode' => 'invalidparameter',
            'message'   => 'Invalid parameter value detected',
        ], 200),
    ]);

    expect(fn () => $this->moodleService->enrollUser(1, 2))
        ->toThrow(UnrecoverableProvisioningException::class);
});

// ─── getTeacherQuizzes ─────────────────────────────────────────────

it('returns empty array in getTeacherQuizzes when user has no enrolled courses', function (): void {
    Http::fake([
        'https://moodle.test/*' => Http::response([], 200),
    ]);

    $result = $this->moodleService->getTeacherQuizzes(55);

    expect($result)->toBe([]);

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $r): bool => $r->data()['wsfunction'] === 'core_enrol_get_users_courses');
});

it('returns empty array in getTeacherQuizzes when user has no teacher permissions', function (): void {
    $sequence = Http::sequence();

    // 1. core_enrol_get_users_courses
    $sequence->push([
        ['id' => 101, 'visible' => 1, 'fullname' => 'Course A'],
    ], 200);

    // 2. core_enrol_get_enrolled_users_with_capability — user 55 is NOT in the teacher list
    $sequence->push([
        [
            'courseid'   => 101,
            'capability' => 'moodle/course:update',
            'users'      => [
                ['id' => 999, 'username' => 'another_user'],
            ],
        ],
    ], 200);

    Http::fake(['https://moodle.test/*' => $sequence]);

    $result = $this->moodleService->getTeacherQuizzes(55);

    expect($result)->toBe([]);
    Http::assertSentCount(2);
});

it('returns quizzes for teacher courses without fetching student grades or completions', function (): void {
    $sequence = Http::sequence();

    // 1. core_enrol_get_users_courses — user enrolled in 2 courses
    $sequence->push([
        ['id' => 101, 'visible' => 1, 'fullname' => 'Teacher Course'],
        ['id' => 102, 'visible' => 1, 'fullname' => 'Student Course'],
    ], 200);

    // 2. core_enrol_get_enrolled_users_with_capability — user 55 is only a teacher in course 101
    $sequence->push([
        [
            'courseid'   => 101,
            'capability' => 'moodle/course:update',
            'users'      => [
                ['id' => 55, 'username' => 'teacher55'],
            ],
        ],
        [
            'courseid'   => 102,
            'capability' => 'moodle/course:update',
            'users'      => [
                ['id' => 888, 'username' => 'someone_else'],
            ],
        ],
    ], 200);

    // 3. mod_quiz_get_quizzes_by_courses — quizzes for course 101
    $sequence->push([
        'quizzes' => [
            ['id' => 1, 'course' => 101, 'coursemodule' => 10, 'name' => 'Midterm Exam', 'visible' => 1],
        ],
    ], 200);

    Http::fake(['https://moodle.test/*' => $sequence]);

    $result = $this->moodleService->getTeacherQuizzes(55);

    expect($result)->toHaveCount(1);
    expect($result[0]->name)->toBe('Teacher Course');
    expect($result[0]->completed)->toBeFalse();
    expect($result[0]->activities)->toHaveCount(1);
    expect($result[0]->activities[0]['cid'])->toBe(10);
    expect($result[0]->activities[0]['name'])->toBe('Midterm Exam');
    expect($result[0]->activities[0]['grade'])->toBeNull();

    // Assert exactly 3 requests were sent (no completion or grade requests)
    Http::assertSentCount(3);
    Http::assertSent(fn (Request $r): bool => $r->data()['wsfunction'] === 'core_enrol_get_users_courses');
    Http::assertSent(fn (Request $r): bool => $r->data()['wsfunction'] === 'core_enrol_get_enrolled_users_with_capability');
    Http::assertSent(fn (Request $r): bool => $r->data()['wsfunction'] === 'mod_quiz_get_quizzes_by_courses');
});

it('returns empty array in getTeacherQuizzes when teacher course has no quizzes', function (): void {
    $sequence = Http::sequence();

    // 1. core_enrol_get_users_courses
    $sequence->push([
        ['id' => 101, 'visible' => 1, 'fullname' => 'Teacher Course'],
    ], 200);

    // 2. core_enrol_get_enrolled_users_with_capability
    $sequence->push([
        [
            'courseid'   => 101,
            'capability' => 'moodle/course:update',
            'users'      => [
                ['id' => 55, 'username' => 'teacher55'],
            ],
        ],
    ], 200);

    // 3. mod_quiz_get_quizzes_by_courses — no quizzes
    $sequence->push([
        'quizzes' => [],
    ], 200);

    Http::fake(['https://moodle.test/*' => $sequence]);

    $result = $this->moodleService->getTeacherQuizzes(55);

    expect($result)->toBe([]);
});

it('does not skip invisible quizzes in getTeacherQuizzes', function (): void {
    $sequence = Http::sequence();

    // 1. core_enrol_get_users_courses
    $sequence->push([
        ['id' => 101, 'visible' => 1, 'fullname' => 'Teacher Course'],
    ], 200);

    // 2. core_enrol_get_enrolled_users_with_capability
    $sequence->push([
        [
            'courseid'   => 101,
            'capability' => 'moodle/course:update',
            'users'      => [
                ['id' => 55, 'username' => 'teacher55'],
            ],
        ],
    ], 200);

    // 3. mod_quiz_get_quizzes_by_courses — hidden quiz
    $sequence->push([
        'quizzes' => [
            ['id' => 1, 'course' => 101, 'coursemodule' => 10, 'name' => 'Hidden Quiz', 'visible' => 0],
        ],
    ], 200);

    Http::fake(['https://moodle.test/*' => $sequence]);

    $result = $this->moodleService->getTeacherQuizzes(55);

    expect($result)->toHaveCount(1);
    expect($result[0]->activities[0]['name'])->toBe('Hidden Quiz');
});

it('includes courses where the user is a non-editing teacher (moodle/grade:viewall capability)', function (): void {
    $sequence = Http::sequence();

    // 1. core_enrol_get_users_courses
    $sequence->push([
        ['id' => 101, 'visible' => 1, 'fullname' => 'Course with Non-Editing Teacher'],
    ], 200);

    // 2. core_enrol_get_enrolled_users_with_capability — non-editing teacher has moodle/grade:viewall
    $sequence->push([
        [
            'courseid'   => 101,
            'capability' => 'moodle/grade:viewall',
            'users'      => [
                ['id' => 55, 'username' => 'non_editing_teacher'],
            ],
        ],
    ], 200);

    // 3. mod_quiz_get_quizzes_by_courses
    $sequence->push([
        'quizzes' => [
            ['id' => 1, 'course' => 101, 'coursemodule' => 10, 'name' => 'Graded Quiz', 'visible' => 1],
        ],
    ], 200);

    Http::fake(['https://moodle.test/*' => $sequence]);

    $result = $this->moodleService->getTeacherQuizzes(55);

    expect($result)->toHaveCount(1);
    expect($result[0]->activities[0]['name'])->toBe('Graded Quiz');
});
