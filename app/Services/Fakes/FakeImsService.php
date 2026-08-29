<?php

declare(strict_types=1);

namespace App\Services\Fakes;

use App\Contracts\Integrations\ImsClientContract;
use App\Enums\User\CivilIdTypeEnum;
use App\Models\User;

/**
 * @codeCoverageIgnore
 */
final class FakeImsService implements ImsClientContract
{
    public function isEnabled(): bool
    {
        return true;
    }

    public function assertConfigured(): void {}

    public function isReady(): bool
    {
        return true;
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function storeStudent(array $payload): array
    {
        return ['data' => ['student_id' => $this->stableId((string) ($payload['external_user_id'] ?? 'student'))]];
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function storeEnrollment(User $user, array $payload): array
    {
        return ['data' => [
            'enrollment_id' => $this->stableId($user->uuid.'|'.(string) ($payload['course_code'] ?? 'course')),
        ]];
    }

    /** @param array<string, mixed> $queryParams @return array<string, mixed> */
    public function getAttendance(string $courseCode, string $teacherCivilId, CivilIdTypeEnum $civilIdType, array $queryParams = []): array
    {
        return ['data' => []];
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function storeAttendance(string $courseCode, string $teacherCivilId, CivilIdTypeEnum $civilIdType, array $payload): array
    {
        return ['data' => $payload];
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function updateAttendance(string $courseCode, string $teacherCivilId, CivilIdTypeEnum $civilIdType, array $payload): array
    {
        return ['data' => $payload];
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function destroyAttendance(string $courseCode, string $teacherCivilId, CivilIdTypeEnum $civilIdType, array $payload): array
    {
        return ['data' => $payload];
    }

    /** @param array<string, mixed> $queryParams @return array<string, mixed> */
    public function getTeacherCourses(string $teacherCivilId, CivilIdTypeEnum $civilIdType, array $queryParams = []): array
    {
        return ['data' => []];
    }

    /** @param array<string, mixed> $queryParams @return array<string, mixed> */
    public function getGrades(string $courseCode, string $teacherCivilId, CivilIdTypeEnum $civilIdType, array $queryParams = []): array
    {
        return ['data' => []];
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function storeGrade(string $courseCode, string $teacherCivilId, CivilIdTypeEnum $civilIdType, array $payload): array
    {
        return ['data' => $payload];
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function storeBulkGrades(string $courseCode, string $teacherCivilId, CivilIdTypeEnum $civilIdType, array $payload): array
    {
        return ['data' => $payload];
    }

    private function stableId(string $value): int
    {
        return (hexdec(mb_substr(hash('sha256', $value), 0, 8)) % 900000) + 100000;
    }
}
