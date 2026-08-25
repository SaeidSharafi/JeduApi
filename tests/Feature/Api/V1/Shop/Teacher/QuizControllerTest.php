<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Shop\Teacher;

use App\Data\Shop\Student\Blocks\LmsMoodleBlockData;
use App\Models\Teacher;
use App\Models\User;
use App\Services\Integrations\MoodleService;
use App\Services\SWRCacheService;
use Laravel\Sanctum\Sanctum;


it('unauthenticated user cannot access teacher quizzes', function (): void {
    $this->getJson('/api/v1/shop/teacher/quizzes')
        ->assertUnauthorized();
});

it('forbids non-teacher users from accessing teacher quizzes', function (): void {
    $student = User::factory()->create();

    $this->customer($student);

    $this->getJson('/api/v1/shop/teacher/quizzes')
        ->assertForbidden();
});

it('returns list of quizzes for authenticated teacher', function (): void {
    $teacherUser = User::factory()->create();
    Teacher::factory()->create([
        'user_id' => $teacherUser->id,
    ]);

    $mockQuizzes = [
        new LmsMoodleBlockData(
            visible: true,
            name: 'Mathematics 101',
            course_url: 'https://moodle.test/course/view.php?id=101',
            completed: false,
            activities: [
                [
                    'url'           => 'https://moodle.test/mod/quiz/view.php?id=10',
                    'cid'           => 10,
                    'name'          => 'Midterm Exam',
                    'type'          => 'quiz',
                    'state'         => 0,
                    'grade'         => null,
                    'timecompleted' => null,
                ],
            ],
        ),
    ];

    $moodleService = $this->mock(MoodleService::class);
    $moodleService->shouldReceive('findOrCreateUser')
        ->once()
        ->with(\Mockery::on(fn (User $user): bool => $user->id === $teacherUser->id))
        ->andReturn([55, 'teacher_username']);

    $moodleService->shouldReceive('getTeacherQuizzes')
        ->once()
        ->with(55)
        ->andReturn($mockQuizzes);

    $this->customer($teacherUser);

    $response = $this->getJson('/api/v1/shop/teacher/quizzes');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'visible',
                    'name',
                    'course_url',
                    'completed',
                    'activities' => [
                        '*' => [
                            'url',
                            'cid',
                            'name',
                            'type',
                            'state',
                            'grade',
                            'timecompleted',
                        ],
                    ],
                ],
            ],
        ])
        ->assertJsonPath('data.0.name', 'Mathematics 101')
        ->assertJsonPath('data.0.activities.0.name', 'Midterm Exam');
});

it('caches teacher quizzes scoped by user id', function (): void {
    $teacherUser = User::factory()->create();
    Teacher::factory()->create([
        'user_id' => $teacherUser->id,
    ]);

    $moodleService = $this->mock(MoodleService::class);
    $moodleService->shouldReceive('findOrCreateUser')
        ->once() // Should only be called once due to cache
        ->andReturn([55, 'teacher_username']);

    $moodleService->shouldReceive('getTeacherQuizzes')
        ->once() // Should only be called once due to cache
        ->with(55)
        ->andReturn([]);

    $this->customer($teacherUser);

    // First request populates cache
    $this->getJson('/api/v1/shop/teacher/quizzes')->assertOk();

    // Second request should hit the SWR cache
    $this->getJson('/api/v1/shop/teacher/quizzes')->assertOk();
});
