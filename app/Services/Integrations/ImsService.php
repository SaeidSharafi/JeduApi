<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Contracts\Integrations\ImsClientContract;
use App\Enums\System\SettingKeyEnum;
use App\Enums\User\CivilIdTypeEnum;
use App\Models\User;
use Illuminate\Support\Facades\Http;

final class ImsService extends AbstractIntegrationService implements ImsClientContract
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function storeStudent(array $payload): array
    {
        $this->assertConfigured();

        if (isset($payload['civil_id']) && ! isset($payload['national_code'])) {
            $payload['national_code'] = $payload['civil_id'];
        }

        if (isset($payload['gender']) && $payload['gender'] === 0) {
            $payload['gender'] = 2;
        }

        $response = Http::baseUrl($this->config['base_url'])
            ->timeout((int) ($this->config['timeout'] ?? 15))
            ->acceptJson()
            ->withToken($this->config['api_key'])
            ->post('/api/v2/student', $payload);

        $this->handleHttpErrors($response, '/api/v2/student');

        return (array) ($response->json() ?? []);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function storeEnrollment(User $user, array $payload): array
    {
        $this->assertConfigured();

        $response = Http::baseUrl($this->config['base_url'])
            ->timeout((int) ($this->config['timeout'] ?? 15))
            ->acceptJson()
            ->withToken($this->config['api_key'])
            ->post('/api/v2/enrollment/', $payload);

        $this->handleHttpErrors($response, '/api/v2/enrollment');

        $result = (array) ($response->json() ?? []);

        if (isset($result['data']['enrolment_id']) && ! isset($result['data']['enrollment_id'])) {
            $result['data']['enrollment_id'] = $result['data']['enrolment_id'];
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $queryParams
     * @return array<string, mixed>
     */
    public function getAttendance(string $courseCode, string $teacherCivilId, CivilIdTypeEnum $civilIdTypeEnum, array $queryParams = []): array
    {
        $this->assertConfigured();

        $response = Http::baseUrl($this->config['base_url'])
            ->timeout((int) ($this->config['timeout'] ?? 15))
            ->acceptJson()
            ->withToken($this->config['api_key'])
            ->withHeaders(['X-Teacher-Civil-Id' => $teacherCivilId])
            ->withHeaders(['X-Teacher-Civil-Id-Type' => $civilIdTypeEnum->value])
            ->get("/api/v2/teacher/course/{$courseCode}/attendance", $queryParams);

        $this->handleHttpErrors($response, "/api/v2/teacher/course/{$courseCode}/attendance");

        return (array) ($response->json() ?? []);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function storeAttendance(string $courseCode, string $teacherCivilId, CivilIdTypeEnum $civilIdTypeEnum, array $payload): array
    {
        $this->assertConfigured();

        $response = Http::baseUrl($this->config['base_url'])
            ->timeout((int) ($this->config['timeout'] ?? 15))
            ->acceptJson()
            ->withToken($this->config['api_key'])
            ->withHeaders(['X-Teacher-Civil-Id' => $teacherCivilId])
            ->withHeaders(['X-Teacher-Civil-Id-Type' => $civilIdTypeEnum->value])
            ->post("/api/v2/teacher/course/{$courseCode}/attendance", $payload);

        $this->handleHttpErrors($response, "/api/v2/teacher/course/{$courseCode}/attendance");

        return (array) ($response->json() ?? []);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateAttendance(string $courseCode, string $teacherCivilId, CivilIdTypeEnum $civilIdTypeEnum, array $payload): array
    {
        $this->assertConfigured();

        $response = Http::baseUrl($this->config['base_url'])
            ->timeout((int) ($this->config['timeout'] ?? 15))
            ->acceptJson()
            ->withToken($this->config['api_key'])
            ->withHeaders(['X-Teacher-Civil-Id' => $teacherCivilId])
            ->withHeaders(['X-Teacher-Civil-Id-Type' => $civilIdTypeEnum->value])
            ->put("/api/v2/teacher/course/{$courseCode}/attendance", $payload);

        $this->handleHttpErrors($response, "/api/v2/teacher/course/{$courseCode}/attendance");

        return (array) ($response->json() ?? []);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function destroyAttendance(string $courseCode, string $teacherCivilId, CivilIdTypeEnum $civilIdTypeEnum, array $payload): array
    {
        $this->assertConfigured();

        $response = Http::baseUrl($this->config['base_url'])
            ->timeout((int) ($this->config['timeout'] ?? 15))
            ->acceptJson()
            ->withToken($this->config['api_key'])
            ->withHeaders(['X-Teacher-Civil-Id' => $teacherCivilId])
            ->withHeaders(['X-Teacher-Civil-Id-Type' => $civilIdTypeEnum->value])
            ->delete("/api/v2/teacher/course/{$courseCode}/attendance", $payload);

        $this->handleHttpErrors($response, "/api/v2/teacher/course/{$courseCode}/attendance");

        return (array) ($response->json() ?? []);
    }

    /**
     * @param  array<string, mixed>  $queryParams
     * @return array<string, mixed>
     */
    public function getTeacherCourses(string $teacherCivilId, CivilIdTypeEnum $civilIdTypeEnum, array $queryParams = []): array
    {
        $this->assertConfigured();

        // Explicitly filter known query params sent to IMS
        $allowed = ['period', 'status', 'is_current', 'past', 'has_grades_enabled', 'has_attendance_enabled'];
        $params  = collect($queryParams)->only($allowed)->toArray();

        $response = Http::baseUrl($this->config['base_url'])
            ->timeout((int) ($this->config['timeout'] ?? 15))
            ->acceptJson()
            ->withToken($this->config['api_key'])
            ->withHeaders(['X-Teacher-Civil-Id' => $teacherCivilId])
            ->withHeaders(['X-Teacher-Civil-Id-Type' => $civilIdTypeEnum->value])
            ->get('/api/v2/teacher/courses', $params);

        $this->handleHttpErrors($response, '/api/v2/teacher/courses');

        return (array) ($response->json() ?? []);
    }

    /**
     * @param  array<string, mixed>  $queryParams
     * @return array<string, mixed>
     */
    public function getGrades(string $courseCode, string $teacherCivilId, CivilIdTypeEnum $civilIdTypeEnum, array $queryParams = []): array
    {
        $this->assertConfigured();

        $response = Http::baseUrl($this->config['base_url'])
            ->timeout((int) ($this->config['timeout'] ?? 15))
            ->acceptJson()
            ->withToken($this->config['api_key'])
            ->withHeaders(['X-Teacher-Civil-Id' => $teacherCivilId])
            ->withHeaders(['X-Teacher-Civil-Id-Type' => $civilIdTypeEnum->value])
            ->get("/api/v2/teacher/course/{$courseCode}/grade", $queryParams);

        $this->handleHttpErrors($response, "/api/v2/teacher/course/{$courseCode}/grade");

        return (array) ($response->json() ?? []);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function storeGrade(string $courseCode, string $teacherCivilId, CivilIdTypeEnum $civilIdTypeEnum, array $payload): array
    {
        $this->assertConfigured();

        $response = Http::baseUrl($this->config['base_url'])
            ->timeout((int) ($this->config['timeout'] ?? 15))
            ->acceptJson()
            ->withToken($this->config['api_key'])
            ->withHeaders(['X-Teacher-Civil-Id' => $teacherCivilId])
            ->withHeaders(['X-Teacher-Civil-Id-Type' => $civilIdTypeEnum->value])
            ->post("/api/v2/teacher/course/{$courseCode}/grade", $payload);

        $this->handleHttpErrors($response, "/api/v2/teacher/course/{$courseCode}/grade");

        return (array) ($response->json() ?? []);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function storeBulkGrades(string $courseCode, string $teacherCivilId, CivilIdTypeEnum $civilIdTypeEnum, array $payload): array
    {
        $this->assertConfigured();

        $response = Http::baseUrl($this->config['base_url'])
            ->timeout((int) ($this->config['timeout'] ?? 15))
            ->acceptJson()
            ->withToken($this->config['api_key'])
            ->withHeaders(['X-Teacher-Civil-Id' => $teacherCivilId])
            ->withHeaders(['X-Teacher-Civil-Id-Type' => $civilIdTypeEnum->value])
            ->post("/api/v2/teacher/course/{$courseCode}/grade/bulk", $payload);

        $this->handleHttpErrors($response, "/api/v2/teacher/course/{$courseCode}/grade/bulk");

        return (array) ($response->json() ?? []);
    }

    protected function getSettingKey(): SettingKeyEnum
    {
        return SettingKeyEnum::IMS;
    }

    protected function getConfigFallbackPath(): string
    {
        return 'services.ims';
    }

    protected function validateConfig(): bool
    {
        return ! empty($this->config['base_url']) && ! empty($this->config['api_key']);
    }
}
