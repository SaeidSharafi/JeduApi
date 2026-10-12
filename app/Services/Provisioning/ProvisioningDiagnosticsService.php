<?php

declare(strict_types=1);

namespace App\Services\Provisioning;

use App\Data\Admin\Enrollment\AdvancedProvisioningDiagnosticsData;
use App\Data\Admin\Enrollment\ProvisioningAttemptDiagnosticData;
use App\Data\Admin\Enrollment\ProvisioningDiagnosticData;
use App\Data\Admin\Enrollment\ProvisioningDiagnosticsData;
use App\Enums\ProvisioningOutcomeStatusEnum;
use App\Helpers\ProvisioningErrorContext;
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
            $references = collect(data_get($outcome, 'data', []))->only(['moodle_user_id', 'moodle_course_id', 'ims_student_id', 'ims_enrollment_id', 'course_code', 'spot_id', 'player_url', 'login_path', 'nili_room_id', 'room_id', 'skyroom_user_id'])->all();

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
        $attempts = $attemptHistory
            ->map(fn (ProvisioningAttempt $attempt): ProvisioningAttemptDiagnosticData => new ProvisioningAttemptDiagnosticData(
                provider: $attempt->provider->value,
                status: $this->diagnosticStatus($attempt->status->value),
                retryable: (bool) $attempt->retryable,
                sequence: (int) $attempt->sequence,
                trigger: $attempt->trigger->value,
                failure_code: $attempt->failure_code,
                classification: $this->classification($attempt->status->value, (bool) $attempt->retryable),
                safe_error: $this->safeError($attempt->failure_message, $attempt->failure_metadata ?? []),
                correlation_id: $attempt->correlation_id,
                context: $this->safeContext($attempt->failure_metadata ?? []),
                created_at: $attempt->created_at?->toISOString(),
            ))
            ->values()
            ->all();

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
            $details = collect(ProvisioningErrorContext::flattenValidationErrors($validationErrors))
                ->map(fn (string $message, string $field): string => $field.': '.$message)
                ->implode('; ');

            return mb_substr('Provider validation failed: '.$details, 0, 1000);
        }

        if (! is_string($error) || $error === '') {
            return null;
        }

        return mb_substr($error, 0, 1000);
    }

    /** @param array<string, mixed> $metadata */
    private function safeContext(array $metadata): array
    {
        // `failure_metadata` is redacted when the attempt is written; re-running the
        // same deny-list keeps rows predating that guarantee safe to return too.
        // An allow-list here previously dropped the response body, function and
        // upstream message, which left the technical detail view with nothing to
        // show and lost any field a provider later added.
        $context = ProvisioningErrorContext::redactSecrets($metadata);

        if (isset($context['endpoint']) && is_string($context['endpoint'])) {
            $context['endpoint'] = parse_url($context['endpoint'], PHP_URL_PATH) ?: Str::before($context['endpoint'], '?');
        }

        return $context;
    }
}
