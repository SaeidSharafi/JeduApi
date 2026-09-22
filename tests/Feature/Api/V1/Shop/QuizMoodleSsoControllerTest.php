<?php

declare(strict_types=1);

use App\Actions\Shop\GenerateQuizMoodleSsoUrlAction;
use App\Data\Shop\Student\MoodleSsoUrlData;
use App\Models\Teacher;
use App\Services\Integrations\MoodleService;

uses(Tests\Support\Traits\AuthTestTrait::class);

covers(GenerateQuizMoodleSsoUrlAction::class);

it('issues student quiz SSO without a Shop enrollment', function (): void {
    $this->customer();
    $ssoData = new MoodleSsoUrlData(
        url: 'https://moodle.test/auth/userkey/login.php?key=abc&wantsurl=quiz',
        wantsurl: '/mod/quiz/view.php?id=42',
    );

    $this->mock(MoodleService::class, function ($mock) use ($ssoData): void {
        $mock->shouldReceive('findOrCreateUser')->once()->andReturn([55, 'student_name']);
        $mock->shouldReceive('canAccessQuiz')->once()->with(55, 42, false)->andReturnTrue();
        $mock->shouldReceive('generateSsoUrl')->once()
            ->with('student_name', '/mod/quiz/view.php?id=42')->andReturn($ssoData);
    });

    $this->postJson(route('api.v1.shop.student.quizzes.moodle.sso', ['courseModuleId' => 42]))
        ->assertOk()
        ->assertJsonPath('data.url', $ssoData->url)
        ->assertJsonPath('data.wantsurl', '/mod/quiz/view.php?id=42');
});

it('returns 404 when the student no longer has access to the quiz', function (): void {
    $this->customer();

    $this->mock(MoodleService::class, function ($mock): void {
        $mock->shouldReceive('findOrCreateUser')->once()->andReturn([55, 'student_name']);
        $mock->shouldReceive('canAccessQuiz')->once()->with(55, 42, false)->andReturnFalse();
        $mock->shouldNotReceive('generateSsoUrl');
    });

    $this->postJson(route('api.v1.shop.student.quizzes.moodle.sso', ['courseModuleId' => 42]))
        ->assertNotFound();
});

it('issues teacher quiz SSO without a Shop delivery option', function (): void {
    $this->customer();
    Teacher::factory()->create(['user_id' => $this->user->id]);

    $ssoData = new MoodleSsoUrlData(
        url: 'https://moodle.test/auth/userkey/login.php?key=abc&wantsurl=quiz',
        wantsurl: '/mod/quiz/view.php?id=42',
    );

    $this->mock(MoodleService::class, function ($mock) use ($ssoData): void {
        $mock->shouldReceive('findOrCreateUser')->once()->andReturn([66, 'teacher_name']);
        $mock->shouldReceive('canAccessQuiz')->once()->with(66, 42, true)->andReturnTrue();
        $mock->shouldReceive('generateSsoUrl')->once()
            ->with('teacher_name', '/mod/quiz/view.php?id=42')->andReturn($ssoData);
    });

    $this->postJson(route('api.v1.shop.teacher.quizzes.moodle.sso', ['courseModuleId' => 42]))
        ->assertOk()
        ->assertJsonPath('data.url', $ssoData->url);
});

it('forbids quiz SSO to an account without a teacher profile', function (): void {
    $this->customer();

    $this->postJson(route('api.v1.shop.teacher.quizzes.moodle.sso', ['courseModuleId' => 42]))
        ->assertForbidden();
});

it('returns 404 when the teacher lacks Moodle course permissions', function (): void {
    $this->customer();
    Teacher::factory()->create(['user_id' => $this->user->id]);

    $this->mock(MoodleService::class, function ($mock): void {
        $mock->shouldReceive('findOrCreateUser')->once()->andReturn([66, 'teacher_name']);
        $mock->shouldReceive('canAccessQuiz')->once()->with(66, 42, true)->andReturnFalse();
        $mock->shouldNotReceive('generateSsoUrl');
    });

    $this->postJson(route('api.v1.shop.teacher.quizzes.moodle.sso', ['courseModuleId' => 42]))
        ->assertNotFound();
});

it('does not accept a client supplied quiz destination', function (): void {
    $this->customer();
    $ssoData = new MoodleSsoUrlData(url: 'https://moodle.test/login?key=abc', wantsurl: '/mod/quiz/view.php?id=42');

    $this->mock(MoodleService::class, function ($mock) use ($ssoData): void {
        $mock->shouldReceive('findOrCreateUser')->once()->andReturn([55, 'student_name']);
        $mock->shouldReceive('canAccessQuiz')->once()->with(55, 42, false)->andReturnTrue();
        $mock->shouldReceive('generateSsoUrl')->once()
            ->with('student_name', '/mod/quiz/view.php?id=42')->andReturn($ssoData);
    });

    $this->postJson(route('api.v1.shop.student.quizzes.moodle.sso', [
        'courseModuleId' => 42,
        'wantsurl'       => 'https://other.test/',
    ]))->assertOk()->assertJsonPath('data.wantsurl', '/mod/quiz/view.php?id=42');
});

it('returns 422 when Moodle cannot generate the quiz login URL', function (): void {
    $this->customer();

    $this->mock(MoodleService::class, function ($mock): void {
        $mock->shouldReceive('findOrCreateUser')->once()->andReturn([55, 'student_name']);
        $mock->shouldReceive('canAccessQuiz')->once()->with(55, 42, false)->andReturnTrue();
        $mock->shouldReceive('generateSsoUrl')->once()
            ->with('student_name', '/mod/quiz/view.php?id=42')->andReturnNull();
    });

    $this->postJson(route('api.v1.shop.student.quizzes.moodle.sso', ['courseModuleId' => 42]))
        ->assertUnprocessable()
        ->assertJsonFragment(['message' => __('messages.enrollments.moodle_service_error')]);
});

it('requires authentication for quiz SSO', function (): void {
    $this->postJson(route('api.v1.shop.student.quizzes.moodle.sso', ['courseModuleId' => 42]))
        ->assertUnauthorized();
});
