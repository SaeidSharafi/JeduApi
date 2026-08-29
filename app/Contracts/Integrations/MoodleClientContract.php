<?php

declare(strict_types=1);

namespace App\Contracts\Integrations;

use App\Data\Shop\Student\Blocks\LmsMoodleBlockData;
use App\Data\Shop\Student\MoodleSsoUrlData;
use App\Models\Enrollment;
use App\Models\User;

interface MoodleClientContract
{
    /** @return array{0: int, 1: string} */
    public function findOrCreateUser(User $user): array;

    public function isCourseCompleted(int $moodleCourseId, int $moodleUserId): bool;

    /** @return array<int, array<string, mixed>> */
    public function getActivityCompletionStatus(int $moodleCourseId, int $moodleUserId): array;

    /** @return array{course_grade: string|null, activities: array<int, string>} */
    public function getGrades(int $moodleCourseId, int $moodleUserId): array;

    public function getCourse(int $moodleCourseId): LmsMoodleBlockData;

    /** @return array<int, LmsMoodleBlockData> */
    public function getAllQuizzes(int $moodleUserId): array;

    /** @return array<int, LmsMoodleBlockData> */
    public function getTeacherQuizzes(int $moodleUserId): array;

    public function enrollUser(
        int $moodleUserId,
        int $moodleCourseId,
        ?int $startTime = null,
        ?int $endTime = null,
        int $roleId = 5
    ): void;

    public function unenrollUser(int $moodleUserId, int $moodleCourseId): void;

    public function createUserKey(string $username, ?string $token = null): string;

    public function generateSsoUrl(string $username, ?string $wantsUrl = null): ?MoodleSsoUrlData;

    public function getCourseWantsUrl(Enrollment $enrollment): ?string;

    public function getDefaultRoleId(): int;

    public function getLoginPath(): string;

    public function isEnabled(): bool;

    public function assertConfigured(): void;

    public function isReady(): bool;
}
