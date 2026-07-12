<?php

declare(strict_types=1);

use App\Services\Integrations\MoodleService;
use App\Services\SWRCacheService;

uses(Tests\Support\Traits\AuthTestTrait::class);

it('returns quizzes for authenticated user', function (): void {
    $this->customer();
    $quizzes = [
        [
            'visible'    => true,
            'name'       => 'Course A',
            'course_url' => '/course/view.php?id=101',
            'completed'  => false,
            'activities' => [
                [
                    'url'     => '/mod/quiz/view.php?id=10',
                    'cid'     => 10,
                    'name'    => 'Quiz 1',
                    'type'    => 'quiz',
                    'state'   => ['value' => 0, 'label' => 'incomplete'],
                    'grade'   => '-',
                    'timecompleted' => null,
                ],
            ],
        ],
    ];

    $this->mock(MoodleService::class, function ($mock) use ($quizzes): void {
        $mock->shouldReceive('findOrCreateUser')
            ->once()
            ->with(Mockery::type(App\Models\User::class))
            ->andReturn([55, 'testuser']);
        $mock->shouldReceive('getAllQuizzes')
            ->once()
            ->with(55)
            ->andReturn($quizzes);
    });

    $response = $this->getJson(route('api.v1.shop.student.quizzes'));

    $response->assertOk();
    $response->assertJsonPath('data', $quizzes);
});

it('returns 401 for unauthenticated request', function (): void {
    $response = $this->getJson('/api/v1/shop/student/quizzes');

    $response->assertStatus(401);
});

it('handles MoodleService exception gracefully', function (): void {
    $this->customer();

    $this->mock(MoodleService::class, function ($mock): void {
        $mock->shouldReceive('findOrCreateUser')
            ->once()
            ->andThrow(new RuntimeException('Moodle API unavailable'));
    });

    $response = $this->getJson(route('api.v1.shop.student.quizzes'));

    expect($response->status())->toBe(500);
});

it('uses SWR cache for quiz data', function (): void {
    $this->customer();

    $quizzes = [
        [
            'visible'    => true,
            'name'       => 'Cached Course',
            'course_url' => '/course/view.php?id=1',
            'completed'  => false,
            'activities' => [],
        ],
    ];

    // Call twice — second call should not hit MoodleService again (SWR cache)
    $this->mock(MoodleService::class, function ($mock) use ($quizzes): void {
        $mock->shouldReceive('findOrCreateUser')
            ->once()
            ->with(Mockery::type(App\Models\User::class))
            ->andReturn([42, 'cached_user']);
        $mock->shouldReceive('getAllQuizzes')
            ->once()
            ->with(42)
            ->andReturn($quizzes);
    });

    $this->getJson(route('api.v1.shop.student.quizzes'))->assertOk();
    $this->getJson(route('api.v1.shop.student.quizzes'))->assertOk();
});
