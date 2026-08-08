<?php

declare(strict_types=1);

namespace App\Services\Fakes;

use App\Data\Shop\Student\Blocks\LmsMoodleBlockData;
use App\Data\Shop\Student\Blocks\MoodleActivityData;
use App\Enums\MoodleActivityStateEnum;
use App\Models\User;
use App\Services\SettingsService;

/**
 * @codeCoverageIgnore
 */
final class FakeMoodleService
{
    private string $baseUrl = 'https://moodle.demo.jedushop.ir';

    private int $timeout = 30;

    public function __construct(private readonly SettingsService $settings) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public function setConfig(array $config): void
    {
        $this->baseUrl = (string) ($config['base_url'] ?? $this->baseUrl);
        $this->timeout = (int) ($config['timeout'] ?? 30);
    }

    /**
     * @return array{0: int, 1: string}
     */
    public function findOrCreateUser(User $user): array
    {
        $username = $user->civil_id ?? 'demo-user-'.$user->id;

        return [1000 + $user->id, (string) $username];
    }

    public function isCourseCompleted(int $moodleCourseId, int $moodleUserId): bool
    {
        return false;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getActivityCompletionStatus(int $moodleCourseId, int $moodleUserId): array
    {
        return [];
    }

    /**
     * @return array{course_grade: string|null, activities: array<int, string>}
     */
    public function getGrades(int $moodleCourseId, int $moodleUserId): array
    {
        return [
            'course_grade' => null,
            'activities'   => [],
        ];
    }

    public function getCourse(int $moodleCourseId): LmsMoodleBlockData
    {
        $baseUrl    = mb_rtrim($this->baseUrl, '/');
        $activities = [
            new MoodleActivityData(
                url: $baseUrl.'/mod/page/view.php?id='.($moodleCourseId * 10 + 1),
                cid: $moodleCourseId * 10 + 1,
                name: 'معرفی دوره',
                type: 'page',
                state: MoodleActivityStateEnum::INCOMPLETE,
            ),
            new MoodleActivityData(
                url: $baseUrl.'/mod/quiz/view.php?id='.($moodleCourseId * 10 + 2),
                cid: $moodleCourseId * 10 + 2,
                name: 'آزمون اول',
                type: 'quiz',
                state: MoodleActivityStateEnum::INCOMPLETE,
            ),
            new MoodleActivityData(
                url: $baseUrl.'/mod/assign/view.php?id='.($moodleCourseId * 10 + 3),
                cid: $moodleCourseId * 10 + 3,
                name: 'تکلیف هفته اول',
                type: 'assign',
                state: MoodleActivityStateEnum::INCOMPLETE,
            ),
        ];

        return new LmsMoodleBlockData(
            visible: true,
            name: 'دوره آموزشی ('.$moodleCourseId.')',
            course_url: $baseUrl.'/course/view.php?id='.$moodleCourseId,
            completed: false,
            activities: $activities,
        );
    }

    public function enrollUser(
        int $moodleUserId,
        int $moodleCourseId,
        ?int $startTime = null,
        ?int $endTime = null,
        int $roleId = 5
    ): void {
        // No-op in demo: enrollment is assumed to succeed.
    }

    public function createUserKey(string $username, ?string $token = null): string
    {
        $baseUrl = mb_rtrim($this->baseUrl, '/');

        return $baseUrl.'/login/index.php?username='.urlencode($username).'&demo_sso=1';
    }
}
