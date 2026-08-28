<?php

declare(strict_types=1);

namespace App\Services\Provisioning;

use App\Contracts\Provisioning\ProvisioningProvider;
use App\Enums\EnrollmentStatusEnum;
use App\Enums\ProvisioningAttemptStatusEnum;
use App\Enums\ProvisioningProviderEnum;
use App\Enums\ProvisioningReadinessEnum;
use App\Enums\ProvisioningStatusEnum;
use App\Enums\ProvisioningTriggerEnum;
use App\Jobs\Provisioning\ProvisionEnrollmentProviderJob;
use App\Models\Enrollment;
use App\Models\ProvisioningAttempt;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ProvisioningAttemptService
{
    public function __construct(private readonly ProvisioningProviderRegistry $providers) {}

    public function queue(
        Enrollment $enrollment,
        ProvisioningTriggerEnum $trigger,
        ?int $staffId = null,
        ProvisioningProviderEnum $provider = ProvisioningProviderEnum::MOODLE
    ): ProvisioningAttempt {
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
            if (! $attempt
                || ! in_array($attempt->status, [
                    ProvisioningAttemptStatusEnum::QUEUED,
                    ProvisioningAttemptStatusEnum::RETRY_SCHEDULED,
                ], true)
            ) {
                return null;
            }

            $attempt->forceFill([
                'status'     => ProvisioningAttemptStatusEnum::RUNNING,
                'running_at' => now(),
            ])->save();

            return $attempt->fresh(['enrollment.customer', 'enrollment.productDeliveryOption']);
        });
    }

    /** @param  array<string, mixed>  $references */
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
            $isAccessReconciliation = $this->isAccessReconciliation($lockedAttempt);
            if ($isAccessReconciliation) {
                data_set($data, 'reconciliation.status', $this->reconciliationStatus($lockedAttempt->enrollment_id));
            }
            $enrollment->provisioning_data = $data;
            if (! $isAccessReconciliation) {
                $this->resolveEnrollment($enrollment);
            }
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

    /** @param  array<string, mixed>  $references */
    public function manuallyResolve(
        Enrollment $enrollment,
        ProvisioningProviderEnum $provider,
        array $references,
        string $reason,
        int $staffId
    ): ProvisioningAttempt {
        return DB::transaction(function () use (
            $enrollment,
            $provider,
            $references,
            $reason,
            $staffId
        ): ProvisioningAttempt {
            $attempt = ProvisioningAttempt::query()->create([
                'enrollment_id' => $enrollment->id,
                'provider'      => $provider,
                'trigger'       => ProvisioningTriggerEnum::MANUAL,
                'status'        => ProvisioningAttemptStatusEnum::SUCCEEDED,
                'sequence'      => ((int) ProvisioningAttempt::query()->where('enrollment_id', $enrollment->id)
                    ->where('provider', $provider->value)->max('sequence')) + 1,
                'retryable'        => false,
                'staff_id'         => $staffId,
                'succeeded_at'     => now(),
                'failure_metadata' => ['reason' => mb_substr($reason, 0, 500)],
            ]);
            $this->mergeManualProviderOutcome($enrollment, $provider, 'success', $references);

            return $attempt;
        });
    }

    public function waive(
        Enrollment $enrollment,
        ProvisioningProviderEnum $provider,
        string $reason,
        int $staffId
    ): ProvisioningAttempt {
        return DB::transaction(function () use ($enrollment, $provider, $reason, $staffId): ProvisioningAttempt {
            $attempt = ProvisioningAttempt::query()->create([
                'enrollment_id' => $enrollment->id,
                'provider'      => $provider,
                'trigger'       => ProvisioningTriggerEnum::MANUAL,
                'status'        => ProvisioningAttemptStatusEnum::MANUAL_ACTION_REQUIRED,
                'sequence'      => ((int) ProvisioningAttempt::query()
                    ->where('enrollment_id', $enrollment->id)->where('provider', $provider->value)->max('sequence'))
                    + 1,
                'retryable'                 => false,
                'staff_id'                  => $staffId,
                'failure_message'           => mb_substr($reason, 0, 1000),
                'failure_metadata'          => ['waived' => true],
                'failed_at'                 => now(),
                'manual_action_required_at' => now(),
            ]);
            $this->mergeManualProviderOutcome($enrollment, $provider, 'waived', []);

            return $attempt;
        });
    }

    public function recalculate(Enrollment $enrollment): void
    {
        $this->resolveEnrollment($enrollment);
    }

    /** @param  array{reason: string, status?: string, access_start_date?: string|null, access_end_date?: string|null}  $context */
    public function recordAccessReconciliation(Enrollment $enrollment, array $context, ?int $staffId = null): void
    {
        $data = $enrollment->provisioning_data ?? [];
        data_set($data, 'reconciliation.status', 'in_progress');
        $enrollment->forceFill(['provisioning_data' => $data])->save();
        $manualActionRequired = false;

        foreach (collect($enrollment->provisioning_plan['providers'] ?? [])->pluck('provider') as $provider) {
            $providerEnum = ProvisioningProviderEnum::tryFrom((string) $provider);
            if (! $providerEnum) {
                continue;
            }

            $adapter       = $this->providers->resolve($providerEnum);
            $references    = data_get($enrollment->provisioning_data, "providers.{$providerEnum->value}.data", []);
            $supported     = $this->supportsAccessReconciliation($adapter, $context, $references);
            $attemptStatus = $supported
                ? ProvisioningAttemptStatusEnum::QUEUED
                : ProvisioningAttemptStatusEnum::MANUAL_ACTION_REQUIRED;
            $failureMessage = $supported ? null : mb_substr($context['reason'], 0, 1000);
            $manualActionAt = $supported ? null : now();

            $attempt = ProvisioningAttempt::query()->create([
                'enrollment_id'    => $enrollment->id,
                'provider'         => $providerEnum,
                'trigger'          => ProvisioningTriggerEnum::MANUAL,
                'status'           => $attemptStatus,
                'sequence'         => $this->nextSequence($enrollment, $providerEnum),
                'retryable'        => false,
                'staff_id'         => $staffId,
                'failure_message'  => $failureMessage,
                'failure_metadata' => [
                    'kind'              => 'access_reconciliation', 'requested_status' => $context['status'] ?? null,
                    'access_start_date' => $context['access_start_date']                                     ?? null,
                    'access_end_date'   => $context['access_end_date']                                       ?? null,
                ],
                'manual_action_required_at' => $manualActionAt,
            ]);
            $manualActionRequired = $manualActionRequired || ! $supported;
            if ($supported) {
                ProvisionEnrollmentProviderJob::dispatch($attempt->id);
            }
        }

        $data                 = $enrollment->fresh()->provisioning_data ?? [];
        $reconciliationStatus = $this->initialReconciliationStatus($enrollment, $manualActionRequired);
        data_set($data, 'reconciliation.status', $reconciliationStatus);
        $enrollment->forceFill(['provisioning_data' => $data])->save();
    }

    /** @param  array<string, mixed>  $metadata */
    public function fail(
        ProvisioningAttempt $attempt,
        Throwable $exception,
        bool $manualAction = false,
        array $metadata = []
    ): void {
        DB::transaction(function () use ($attempt, $exception, $manualAction, $metadata): void {
            $locked = ProvisioningAttempt::query()->lockForUpdate()->find($attempt->id);
            if (! $locked || $locked->status !== ProvisioningAttemptStatusEnum::RUNNING) {
                return;
            }

            $status = $manualAction ? ProvisioningAttemptStatusEnum::MANUAL_ACTION_REQUIRED
                : ProvisioningAttemptStatusEnum::FAILED;
            $isAccessReconciliation = $this->isAccessReconciliation($locked);
            $safeMetadata           = $this->safeMetadata($metadata);
            if ($isAccessReconciliation) {
                $safeMetadata['kind'] = 'access_reconciliation';
            }
            $locked->forceFill([
                'status'                    => $status,
                'retryable'                 => false,
                'failure_code'              => $exception->getCode() ? (string) $exception->getCode() : null,
                'failure_message'           => mb_substr($exception->getMessage(), 0, 1000),
                'failure_metadata'          => $safeMetadata,
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
            if ($isAccessReconciliation) {
                data_set($data, 'reconciliation.status', $manualAction ? 'manual_action_required' : 'failed');
            }
            $enrollment->provisioning_data = $data;
            if (! $isAccessReconciliation) {
                $enrollment->provisioning_status = $manualAction
                    ? ProvisioningStatusEnum::MANUAL_ACTION_REQUIRED
                    : ProvisioningStatusEnum::DEGRADED;
                $enrollment->enrollment_status = EnrollmentStatusEnum::PROVISIONING_FAILED;
            }
            $enrollment->save();
        });
    }

    /** @param  array<string, mixed>  $references */
    private function mergeManualProviderOutcome(
        Enrollment $enrollment,
        ProvisioningProviderEnum $provider,
        string $status,
        array $references
    ): void {
        $enrollment->refresh();
        $data = $enrollment->provisioning_data ?? [];
        data_set($data, "providers.{$provider->value}", [
            'status'           => $status,
            'attempt_sequence' => ((int) data_get($data, "providers.{$provider->value}.attempt_sequence", 0)) + 1,
            'data'             => $this->safeReferences($references),
        ]);
        $enrollment->provisioning_data = $data;
        $this->resolveEnrollment($enrollment);
        $enrollment->save();
    }

    private function resolveEnrollment(Enrollment $enrollment): void
    {
        $planned = $enrollment->provisioning_plan['providers'] ?? [];

        if ($planned !== [] && $enrollment->hasHealthyProvisioningOutcomes()) {
            $enrollment->enrollment_status   = EnrollmentStatusEnum::ACTIVE;
            $enrollment->provisioning_status = ProvisioningStatusEnum::HEALTHY;
        } elseif ($planned === []) {
            $enrollment->enrollment_status   = EnrollmentStatusEnum::ACTIVE;
            $enrollment->provisioning_status = ProvisioningStatusEnum::HEALTHY;
        } elseif ($this->hasUnreadyProvider($planned)) {
            $enrollment->enrollment_status   = EnrollmentStatusEnum::PROVISIONING_FAILED;
            $enrollment->provisioning_status = ProvisioningStatusEnum::MANUAL_ACTION_REQUIRED;
        } elseif ($this->hasFailedProvider($enrollment, $planned)) {
            $enrollment->enrollment_status   = EnrollmentStatusEnum::PROVISIONING_FAILED;
            $enrollment->provisioning_status = ProvisioningStatusEnum::DEGRADED;
        } else {
            $enrollment->enrollment_status   = EnrollmentStatusEnum::PROVISIONING_FAILED;
            $enrollment->provisioning_status = ProvisioningStatusEnum::IN_PROGRESS;
        }
    }

    /** @param array<string, mixed> $context @param array<string, mixed> $references */
    private function supportsAccessReconciliation(
        ProvisioningProvider $adapter,
        array $context,
        array $references
    ): bool {
        $supportedStatuses = [
            EnrollmentStatusEnum::ACTIVE->value,
            EnrollmentStatusEnum::SUSPENDED->value,
            EnrollmentStatusEnum::EXPIRED->value,
            EnrollmentStatusEnum::CANCELLED->value,
        ];

        return $adapter->supportsAccessReconciliation()
            && in_array($context['status'] ?? null, $supportedStatuses, true)
            && $references !== [];
    }

    private function nextSequence(Enrollment $enrollment, ProvisioningProviderEnum $provider): int
    {
        $latestSequence = ProvisioningAttempt::query()
            ->where('enrollment_id', $enrollment->id)
            ->where('provider', $provider->value)
            ->max('sequence');

        return ((int) $latestSequence) + 1;
    }

    private function initialReconciliationStatus(Enrollment $enrollment, bool $manualActionRequired): string
    {
        if (! $enrollment->hasRequiredProvisioningProviders()) {
            return 'not_applicable';
        }

        return $manualActionRequired ? 'manual_action_required' : 'in_progress';
    }

    /** @param array<int, array<string, mixed>> $planned */
    private function hasUnreadyProvider(array $planned): bool
    {
        foreach ($planned as $provider) {
            if (($provider['readiness'] ?? null) !== ProvisioningReadinessEnum::READY->value) {
                return true;
            }
        }

        return false;
    }

    /** @param array<int, array<string, mixed>> $planned */
    private function hasFailedProvider(Enrollment $enrollment, array $planned): bool
    {
        foreach ($planned as $provider) {
            $status = data_get($enrollment->provisioning_data, "providers.{$provider['provider']}.status");
            if ($status === 'failed') {
                return true;
            }
        }

        return false;
    }

    private function isAccessReconciliation(ProvisioningAttempt $attempt): bool
    {
        return data_get($attempt->failure_metadata, 'kind') === 'access_reconciliation';
    }

    private function reconciliationStatus(int $enrollmentId): string
    {
        $statuses = ProvisioningAttempt::query()
            ->where('enrollment_id', $enrollmentId)
            ->where('failure_metadata->kind', 'access_reconciliation')
            ->latest('id')
            ->get()
            ->groupBy(fn (ProvisioningAttempt $attempt): string => $attempt->provider->value)
            ->map(fn (Collection $attempts): ProvisioningAttemptStatusEnum => $attempts->first()->status);

        if ($statuses->contains(fn (ProvisioningAttemptStatusEnum $status): bool => $status
            === ProvisioningAttemptStatusEnum::MANUAL_ACTION_REQUIRED)
        ) {
            return 'manual_action_required';
        }

        if ($statuses->contains(fn (ProvisioningAttemptStatusEnum $status): bool => $status
            === ProvisioningAttemptStatusEnum::FAILED)
        ) {
            return 'failed';
        }

        if ($statuses->every(fn (ProvisioningAttemptStatusEnum $status): bool => $status
            === ProvisioningAttemptStatusEnum::SUCCEEDED)
        ) {
            return 'succeeded';
        }

        return 'in_progress';
    }

    /** @param  array<string, mixed>  $metadata  @return array<string, mixed> */
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

    /** @param  array<string, mixed>  $references  @return array<string, mixed> */
    private function safeReferences(array $references): array
    {
        return collect($references)->only([
            'moodle_user_id', 'moodle_user_name', 'moodle_username', 'moodle_course_id', 'ims_student_id',
            'ims_enrollment_id', 'course_code', 'spot_id', 'license_key', 'player_url', 'login_path', 'meeting_id',
            'nili_room_id', 'room_id', 'skyroom_user_id', 'provisioned_at',
        ])->all();
    }
}
