<?php

declare(strict_types=1);

namespace App\Services\Provisioning;

use App\Enums\EnrollmentStatusEnum;
use App\Enums\ProvisioningAttemptStatusEnum;
use App\Enums\ProvisioningProviderEnum;
use App\Enums\ProvisioningReadinessEnum;
use App\Enums\ProvisioningStatusEnum;
use App\Enums\ProvisioningTriggerEnum;
use App\Models\Enrollment;
use App\Models\ProvisioningAttempt;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ProvisioningAttemptService
{
    public function queue(Enrollment $enrollment, ProvisioningTriggerEnum $trigger, ?int $staffId = null, ProvisioningProviderEnum $provider = ProvisioningProviderEnum::MOODLE): ProvisioningAttempt
    {
        return DB::transaction(function () use ($enrollment, $trigger, $staffId, $provider): ProvisioningAttempt {
            Enrollment::query()->lockForUpdate()->findOrFail($enrollment->id);
            $active = ProvisioningAttempt::query()
                ->where('enrollment_id', $enrollment->id)
                ->where('provider', $provider->value)
                ->whereIn('status', [
                    ProvisioningAttemptStatusEnum::QUEUED,
                    ProvisioningAttemptStatusEnum::RUNNING,
                    ProvisioningAttemptStatusEnum::RETRY_SCHEDULED,
                ])
                ->latest('id')
                ->first();
            if ($active) {
                return $active;
            }
            $sequence = ((int) ProvisioningAttempt::query()
                ->where('enrollment_id', $enrollment->id)
                ->where('provider', $provider->value)
                ->max('sequence')) + 1;

            return ProvisioningAttempt::query()->create([
                'enrollment_id' => $enrollment->id,
                'provider'      => $provider,
                'trigger'       => $trigger,
                'status'        => ProvisioningAttemptStatusEnum::QUEUED,
                'sequence'      => $sequence,
                'retryable'     => true,
                'staff_id'      => $staffId,
                'queued_at'     => now(),
            ]);
        });
    }

    public function start(int $attemptId): ?ProvisioningAttempt
    {
        return DB::transaction(function () use ($attemptId): ?ProvisioningAttempt {
            $attempt = ProvisioningAttempt::query()->lockForUpdate()->find($attemptId);
            if (! $attempt || ! in_array($attempt->status, [
                ProvisioningAttemptStatusEnum::QUEUED,
                ProvisioningAttemptStatusEnum::RETRY_SCHEDULED,
            ], true)) {
                return null;
            }

            $attempt->forceFill([
                'status'     => ProvisioningAttemptStatusEnum::RUNNING,
                'running_at' => now(),
            ])->save();

            return $attempt->fresh(['enrollment.customer', 'enrollment.productDeliveryOption']);
        });
    }

    /** @param array<string, mixed> $references */
    public function succeed(ProvisioningAttempt $attempt, array $references): void
    {
        DB::transaction(function () use ($attempt, $references): void {
            $lockedAttempt = ProvisioningAttempt::query()->lockForUpdate()->find($attempt->id);
            if (! $lockedAttempt || $lockedAttempt->status !== ProvisioningAttemptStatusEnum::RUNNING) {
                return;
            }

            $lockedAttempt->forceFill([
                'status'       => ProvisioningAttemptStatusEnum::SUCCEEDED,
                'retryable'    => false,
                'succeeded_at' => now(),
            ])->save();

            $enrollment = Enrollment::query()->lockForUpdate()->find($lockedAttempt->enrollment_id);
            if (! $enrollment) {
                return;
            }

            $data             = $enrollment->provisioning_data ?? [];
            $provider         = $lockedAttempt->provider->value;
            $existingSequence = (int) data_get($data, "providers.{$provider}.attempt_sequence", 0);
            if ($lockedAttempt->sequence < $existingSequence) {
                return;
            }

            data_set($data, "providers.{$provider}", [
                'status'           => 'success',
                'attempt_sequence' => $lockedAttempt->sequence,
                'data'             => $this->safeReferences($references),
            ]);
            $enrollment->provisioning_data = $data;
            $this->resolveEnrollment($enrollment);
            $enrollment->save();
        });
    }

    public function scheduleRetry(ProvisioningAttempt $attempt): void
    {
        DB::transaction(function () use ($attempt): void {
            $locked = ProvisioningAttempt::query()->lockForUpdate()->find($attempt->id);
            if (! $locked || $locked->status !== ProvisioningAttemptStatusEnum::RUNNING) {
                return;
            }
            $locked->forceFill([
                'status'             => ProvisioningAttemptStatusEnum::RETRY_SCHEDULED,
                'retry_scheduled_at' => now(),
            ])->save();
        });
    }

    /** @param array<string, mixed> $metadata */
    public function fail(ProvisioningAttempt $attempt, Throwable $exception, bool $manualAction = false, array $metadata = []): void
    {
        DB::transaction(function () use ($attempt, $exception, $manualAction, $metadata): void {
            $locked = ProvisioningAttempt::query()->lockForUpdate()->find($attempt->id);
            if (! $locked || $locked->status !== ProvisioningAttemptStatusEnum::RUNNING) {
                return;
            }

            $status = $manualAction ? ProvisioningAttemptStatusEnum::MANUAL_ACTION_REQUIRED : ProvisioningAttemptStatusEnum::FAILED;
            $locked->forceFill([
                'status'                    => $status,
                'retryable'                 => false,
                'failure_code'              => $exception->getCode() ? (string) $exception->getCode() : null,
                'failure_message'           => mb_substr($exception->getMessage(), 0, 1000),
                'failure_metadata'          => $this->safeMetadata($metadata),
                'failed_at'                 => now(),
                'manual_action_required_at' => $manualAction ? now() : null,
            ])->save();

            $enrollment = Enrollment::query()->lockForUpdate()->find($locked->enrollment_id);
            if (! $enrollment) {
                return;
            }
            $data             = $enrollment->provisioning_data ?? [];
            $provider         = $locked->provider->value;
            $existingSequence = (int) data_get($data, "providers.{$provider}.attempt_sequence", 0);
            if ($locked->sequence < $existingSequence) {
                return;
            }
            data_set($data, "providers.{$provider}", [
                'status'           => $manualAction ? 'manual_action_required' : 'failed',
                'attempt_sequence' => $locked->sequence,
                'last_error'       => mb_substr($exception->getMessage(), 0, 1000),
            ]);
            $enrollment->provisioning_data   = $data;
            $enrollment->provisioning_status = $manualAction
                ? ProvisioningStatusEnum::MANUAL_ACTION_REQUIRED
                : ProvisioningStatusEnum::DEGRADED;
            $enrollment->enrollment_status = EnrollmentStatusEnum::PROVISIONING_FAILED;
            $enrollment->save();
        });
    }

    private function resolveEnrollment(Enrollment $enrollment): void
    {
        $planned       = $enrollment->provisioning_plan['providers'] ?? [];
        $allSuccessful = $planned !== [] && collect($planned)->every(
            fn (array $provider): bool => data_get($enrollment->provisioning_data, "providers.{$provider['provider']}.status") === 'success'
        );
        if ($allSuccessful) {
            $enrollment->enrollment_status   = EnrollmentStatusEnum::ACTIVE;
            $enrollment->provisioning_status = ProvisioningStatusEnum::HEALTHY;
        } elseif ($planned === []) {
            $enrollment->enrollment_status   = EnrollmentStatusEnum::ACTIVE;
            $enrollment->provisioning_status = ProvisioningStatusEnum::HEALTHY;
        } elseif (collect($planned)->contains(fn (array $provider): bool => ($provider['readiness'] ?? null) !== ProvisioningReadinessEnum::READY->value)) {
            $enrollment->provisioning_status = ProvisioningStatusEnum::MANUAL_ACTION_REQUIRED;
        } else {
            $enrollment->provisioning_status = ProvisioningStatusEnum::IN_PROGRESS;
        }
    }

    /** @param array<string, mixed> $metadata @return array<string, mixed> */
    private function safeMetadata(array $metadata): array
    {
        $safe = collect($metadata)->only(['http_status', 'endpoint', 'errorcode'])->all();
        if (isset($metadata['validation_errors']) && is_array($metadata['validation_errors'])) {
            $safe['validation_errors'] = collect($metadata['validation_errors'])
                ->map(function (mixed $value): string {
                    $serialized = is_scalar($value) ? (string) $value : (json_encode($value) ?: '[unavailable]');

                    return mb_substr($serialized, 0, 500);
                })
                ->all();
        }

        return $safe;
    }

    /** @param array<string, mixed> $references @return array<string, mixed> */
    private function safeReferences(array $references): array
    {
        return collect($references)->only([
            'moodle_user_id', 'moodle_user_name', 'moodle_username', 'moodle_course_id', 'ims_student_id', 'ims_enrollment_id', 'course_code', 'spot_id', 'license_key', 'player_url', 'login_path', 'provisioned_at',
        ])->all();
    }
}
