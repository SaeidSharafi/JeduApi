<?php

declare(strict_types=1);

namespace App\Services\Provisioning;

use App\Data\Admin\Enrollment\AdvancedProvisioningDiagnosticsData;
use App\Data\Admin\Enrollment\ProvisioningAttemptDiagnosticData;
use App\Data\Admin\Enrollment\ProvisioningDiagnosticData;
use App\Data\Admin\Enrollment\ProvisioningDiagnosticsData;
use App\Enums\ProvisioningOutcomeStatusEnum;
use App\Models\Enrollment;
use App\Models\ProvisioningAttempt;
use Illuminate\Support\Str;
use Spatie\LaravelData\DataCollection;

final class ProvisioningDiagnosticsService
{
    public function diagnostics(Enrollment $enrollment, bool $advanced = false): AdvancedProvisioningDiagnosticsData|ProvisioningDiagnosticsData
    {
        $data           = $enrollment->provisioning_data ?? [];
        $attemptHistory = $enrollment->provisioningAttempts()->latest('id')->get();
        $latestAttempts = $attemptHistory->groupBy(fn (ProvisioningAttempt $attempt): string => $attempt->provider->value);
        $providers      = collect($enrollment->provisioning_plan['providers'] ?? [])->map(function (array $plan) use ($data, $latestAttempts): ProvisioningDiagnosticData {
            $provider   = (string) $plan['provider'];
            $outcome    = data_get($data, "providers.{$provider}", []);
            $status     = (string) ($outcome['status'] ?? 'pending');
            $status     = $status === ProvisioningOutcomeStatusEnum::SUCCESS->value ? 'succeeded' : $status;
            $references = collect(data_get($outcome, 'data', []))->only(['moodle_user_id', 'moodle_course_id', 'ims_student_id', 'ims_enrollment_id', 'course_code', 'spot_id', 'player_url', 'login_path', 'meeting_id', 'nili_room_id', 'room_id', 'skyroom_user_id'])->all();

            $attempt = $latestAttempts->get($provider)?->first();

            return new ProvisioningDiagnosticData($provider, $status, (bool) ($attempt?->retryable ?? $status === 'failed'), $status === 'succeeded' || $status === 'waived' ? 'none' : ($status === 'failed' ? 'retry_or_manual_review' : 'await_provisioning'), $this->safeError(data_get($outcome, 'last_error'), $attempt?->failure_metadata ?? []), $references, $attempt?->updated_at?->toISOString() ?? data_get($outcome, 'updated_at'));
        })->values()->all();
        $summary = new ProvisioningDiagnosticsData(
            (string) $enrollment->provisioning_status->value,
            new DataCollection(ProvisioningDiagnosticData::class, $providers),
            data_get($data, 'reconciliation.status'),
        );
        if (! $advanced) {
            return $summary;
        }
        $attempts = $attemptHistory->map(fn (ProvisioningAttempt $attempt): ProvisioningAttemptDiagnosticData => new ProvisioningAttemptDiagnosticData($attempt->provider->value, $this->diagnosticStatus($attempt->status->value), (bool) $attempt->retryable, (int) $attempt->sequence, $attempt->trigger->value, $attempt->failure_code, $this->classification($attempt->status->value, (bool) $attempt->retryable), $attempt->correlation_id, $this->safeContext($attempt->failure_metadata ?? []), $attempt->created_at?->toISOString()))->values()->all();

        return new AdvancedProvisioningDiagnosticsData($summary, new DataCollection(ProvisioningAttemptDiagnosticData::class, $attempts));
    }

    private function diagnosticStatus(string $status): string
    {
        return $status === 'succeeded' ? 'succeeded' : $status;
    }

    private function classification(string $status, bool $retryable): string
    {
        if ($status === 'manual_action_required') {
            return 'manual_action_required';
        }

        if ($status === 'failed' && $retryable) {
            return 'recoverable';
        }

        if ($status === 'failed') {
            return 'terminal';
        }

        return 'not_applicable';
    }

    /** @param array<string, mixed> $metadata */
    private function safeError(mixed $error, array $metadata = []): ?string
    {
        $validationErrors = data_get($metadata, 'validation_errors');
        if (is_array($validationErrors) && $validationErrors !== []) {
            $details = collect($this->safeValidationErrors($validationErrors))
                ->map(fn (string $message, string $field): string => $field.': '.$message)
                ->implode('; ');

            return mb_substr('Provider validation failed: '.$details, 0, 1000);
        }

        if (! is_string($error) || $error === '') {
            return null;
        }

        return 'Provider failure details were redacted.';
    }

    /** @param array<string, mixed> $validationErrors @return array<string, string> */
    private function safeValidationErrors(array $validationErrors): array
    {
        return collect($validationErrors)
            ->map(function (mixed $value): string {
                $message   = is_scalar($value) ? (string) $value : (json_encode($value) ?: '[unavailable]');
                $sanitized = preg_replace(
                    ['/[\w.+-]+@[\w-]+\.[\w.-]+/', '/\b09\d{9}\b/', '/\b\d{10}\b/'],
                    '[REDACTED]',
                    $message
                );

                return mb_substr($sanitized ?? $message, 0, 500);
            })
            ->all();
    }

    /** @param array<string, mixed> $metadata */
    private function safeContext(array $metadata): array
    {
        $context = collect($metadata)->only(['http_status', 'errorcode'])->all();
        if (isset($metadata['endpoint']) && is_string($metadata['endpoint'])) {
            $context['endpoint'] = parse_url($metadata['endpoint'], PHP_URL_PATH) ?: Str::before($metadata['endpoint'], '?');
        }

        if (isset($metadata['validation_errors']) && is_array($metadata['validation_errors'])) {
            $context['validation_errors'] = $this->safeValidationErrors($metadata['validation_errors']);
        }

        return $context;
    }
}
