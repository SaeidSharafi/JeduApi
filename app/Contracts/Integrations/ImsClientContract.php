<?php

declare(strict_types=1);

namespace App\Contracts\Integrations;

use App\Enums\User\CivilIdTypeEnum;
use App\Models\User;

interface ImsClientContract
{
    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function storeStudent(array $payload): array;

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function storeEnrollment(User $user, array $payload): array;

    /** @param array<string, mixed> $queryParams @return array<string, mixed> */
    public function getAttendance(string $courseCode, string $teacherCivilId, CivilIdTypeEnum $civilIdType, array $queryParams = []): array;

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function storeAttendance(string $courseCode, string $teacherCivilId, CivilIdTypeEnum $civilIdType, array $payload): array;

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function updateAttendance(string $courseCode, string $teacherCivilId, CivilIdTypeEnum $civilIdType, array $payload): array;

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function destroyAttendance(string $courseCode, string $teacherCivilId, CivilIdTypeEnum $civilIdType, array $payload): array;

    /** @param array<string, mixed> $queryParams @return array<string, mixed> */
    public function getTeacherCourses(string $teacherCivilId, CivilIdTypeEnum $civilIdType, array $queryParams = []): array;

    /** @param array<string, mixed> $queryParams @return array<string, mixed> */
    public function getGrades(string $courseCode, string $teacherCivilId, CivilIdTypeEnum $civilIdType, array $queryParams = []): array;

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function storeGrade(string $courseCode, string $teacherCivilId, CivilIdTypeEnum $civilIdType, array $payload): array;

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function storeBulkGrades(string $courseCode, string $teacherCivilId, CivilIdTypeEnum $civilIdType, array $payload): array;

    public function isEnabled(): bool;

    public function assertConfigured(): void;

    public function isReady(): bool;
}
