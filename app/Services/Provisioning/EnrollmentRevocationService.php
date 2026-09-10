<?php

declare(strict_types=1);

namespace App\Services\Provisioning;

use App\Contracts\Provisioning\RevocationProvider;
use App\Enums\EnrollmentRevocationStatusEnum;
use App\Enums\EnrollmentStatusEnum;
use App\Enums\ProvisioningAttemptStatusEnum;
use App\Enums\ProvisioningOutcomeStatusEnum;
use App\Enums\ProvisioningProviderEnum;
use App\Enums\ProvisioningTriggerEnum;
use App\Jobs\Provisioning\RevokeEnrollmentProviderJob;
use App\Models\Enrollment;
use App\Models\ProvisioningAttempt;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Owns the per-Enrollment external provider revocation lifecycle.
 *
 * Revocation is deliberately separate from provisioning health: a component
 * Enrollment whose Bundle Purchase was refunded keeps its seat and its
 * provisioning state, but enters `revocation_status`. Purchase Eligibility
 * stays blocked until every required provider revocation has succeeded. A
 * successful revocation is terminal and is never restored because a sibling
 * component failed.
 */
final class EnrollmentRevocationService
{
    public function __construct(
        private readonly ProvisioningProviderRegistry $providers,
        private readonly ProvisioningAttemptService $attempts,
    ) {}

    /**
     * Mark the Enrollment revocation-pending and queue one attempt per required
     * provider. Returns the queued attempt ids so the caller can dispatch the
     * jobs only after its surrounding transaction commits.
     *
     * @return list<int>
     */
    public function begin(Enrollment $enrollment): array
    {
        return DB::transaction(function () use ($enrollment): array {
            $locked = Enrollment::query()->lockForUpdate()->findOrFail($enrollment->id);
            if ($locked->isRevocationComplete()) {
                return [];
            }

            $required = $this->requiredProviders($locked);
            $queued   = [];
            $active   = false;
            $manual   = false;
            foreach ($required as $provider) {
                if ($this->providerRevoked($locked, $provider)) {
                    continue;
                }

                // The database allows one active attempt per provider across
                // provisioning, reconciliation, and revocation. When a
                // reconciliation is already running, wait for it instead of
                // creating a competing revocation attempt.
                if ($this->hasActiveAttempt($locked, $provider)) {
                    $active = true;

                    continue;
                }

                $attemptId = $this->queueOrFlagManual($locked, $provider);
                if ($attemptId !== null) {
                    $queued[] = $attemptId;
                } else {
                    $manual = true;
                }
            }

            $locked->revocation_status = match (true) {
                $required === []          => EnrollmentRevocationStatusEnum::REVOKED,
                $queued !== [] || $active => EnrollmentRevocationStatusEnum::PENDING,
                $manual                   => EnrollmentRevocationStatusEnum::MANUAL_ACTION_REQUIRED,
                default                   => EnrollmentRevocationStatusEnum::PENDING,
            };
            $locked->enrollment_status = $required === []
                ? EnrollmentStatusEnum::CANCELLED
                : EnrollmentStatusEnum::SUSPENDED;
            $locked->save();

            return $queued;
        });
    }

    /**
     * Queue revocation for every required provider that has not succeeded yet.
     * Succeeded providers are never touched, so retrying one component cannot
     * undo another component's completed revocation.
     *
     * @return list<int>
     */
    public function retry(Enrollment $enrollment): array
    {
        return DB::transaction(function () use ($enrollment): array {
            $locked = Enrollment::query()->lockForUpdate()->findOrFail($enrollment->id);
            if ($locked->isRevocationComplete()) {
                return [];
            }

            $queued = [];
            foreach ($this->requiredProviders($locked) as $provider) {
                if ($this->providerRevoked($locked, $provider) || $this->hasActiveAttempt($locked, $provider)) {
                    continue;
                }

                $attemptId = $this->queueOrFlagManual($locked, $provider);
                if ($attemptId !== null) {
                    $queued[] = $attemptId;
                }
            }

            $this->recalculate($locked);
            $locked->save();

            return $queued;
        });
    }

    /**
     * Staff confirmation that an externally unsupported revocation was carried
     * out manually. Records a succeeded attempt per outstanding provider.
     */
    public function confirmManually(Enrollment $enrollment, int $staffId, ?string $reason = null): void
    {
        DB::transaction(function () use ($enrollment, $staffId, $reason): void {
            $locked = Enrollment::query()->lockForUpdate()->findOrFail($enrollment->id);
            if ($locked->isRevocationComplete()) {
                return;
            }

            foreach ($this->requiredProviders($locked) as $provider) {
                if ($this->providerRevoked($locked, $provider)) {
                    continue;
                }

                ProvisioningAttempt::query()->create([
                    'enrollment_id'    => $locked->id,
                    'provider'         => $provider,
                    'trigger'          => ProvisioningTriggerEnum::REVOCATION,
                    'status'           => ProvisioningAttemptStatusEnum::SUCCEEDED,
                    'sequence'         => $this->nextSequence($locked, $provider),
                    'retryable'        => false,
                    'staff_id'         => $staffId,
                    'succeeded_at'     => now(),
                    'failure_metadata' => array_filter([
                        'kind'                => 'revocation',
                        'manual_confirmation' => true,
                        'reason'              => $reason,
                    ], fn (mixed $value): bool => $value !== null),
                ]);
            }

            $this->recalculate($locked);
            $locked->save();
        });
    }

    /** @param array<string, mixed> $references */
    public function succeed(ProvisioningAttempt $attempt, array $references): void
    {
        DB::transaction(function () use ($attempt, $references): void {
            $locked = ProvisioningAttempt::query()->lockForUpdate()->find($attempt->id);
            if (! $locked || $locked->status !== ProvisioningAttemptStatusEnum::RUNNING) {
                return;
            }

            $locked->forceFill([
                'status'       => ProvisioningAttemptStatusEnum::SUCCEEDED,
                'retryable'    => false,
                'succeeded_at' => now(),
            ])->save();

            $enrollment = Enrollment::query()->lockForUpdate()->find($locked->enrollment_id);
            if (! $enrollment || $enrollment->isRevocationComplete()) {
                return;
            }

            $data = $enrollment->provisioning_data ?? [];
            data_set($data, "revocation.providers.{$locked->provider->value}", [
                'status'           => EnrollmentRevocationStatusEnum::REVOKED->value,
                'attempt_sequence' => $locked->sequence,
                'data'             => $this->safeReferences($references),
                'revoked_at'       => now()->toISOString(),
            ]);
            $enrollment->provisioning_data = $data;

            $this->recalculate($enrollment);
            $enrollment->save();
        });
    }

    /**
     * Converge a started revocation after the legacy access-reconciliation
     * path removed provider access. Only applies to Enrollments that are
     * already in a revocation flow; ordinary staff status changes are not
     * affected.
     */
    public function syncFromReconciliation(Enrollment $enrollment): void
    {
        DB::transaction(function () use ($enrollment): void {
            $locked = Enrollment::query()->lockForUpdate()->find($enrollment->id);
            if (! $locked || $locked->revocation_status === null || $locked->isRevocationComplete()) {
                return;
            }

            $this->recalculate($locked);
            if ($locked->isRevocationComplete()) {
                $locked->save();
            }
        });
    }

    /** @param array<string, mixed> $metadata */
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

            $locked->forceFill([
                'status' => $manualAction
                    ? ProvisioningAttemptStatusEnum::MANUAL_ACTION_REQUIRED
                    : ProvisioningAttemptStatusEnum::FAILED,
                'retryable'        => true,
                'failure_code'     => $exception->getCode() ? (string) $exception->getCode() : null,
                'failure_message'  => mb_substr($exception->getMessage(), 0, 1000),
                'failure_metadata' => array_filter([
                    'kind'        => 'revocation',
                    'http_status' => $metadata['http_status'] ?? null,
                    'endpoint'    => $metadata['endpoint']    ?? null,
                    'errorcode'   => $metadata['errorcode']   ?? null,
                ], fn (mixed $value): bool => $value !== null),
                'failed_at'                 => now(),
                'manual_action_required_at' => $manualAction ? now() : null,
            ])->save();

            $enrollment = Enrollment::query()->lockForUpdate()->find($locked->enrollment_id);
            if (! $enrollment || $enrollment->isRevocationComplete()) {
                return;
            }

            $this->recalculate($enrollment);
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

            $enrollment = Enrollment::query()->lockForUpdate()->find($locked->enrollment_id);
            if (! $enrollment || $enrollment->isRevocationComplete()) {
                return;
            }

            $this->recalculate($enrollment);
            $enrollment->save();
        });
    }

    /** @param list<int> $attemptIds */
    public function dispatchAttempts(array $attemptIds): void
    {
        foreach ($attemptIds as $attemptId) {
            RevokeEnrollmentProviderJob::dispatch($attemptId);
        }
    }

    /**
     * Providers that actually granted external access and therefore must be
     * revoked. Providers that only failed, were waived, or were never
     * provisioned grant nothing and are skipped.
     *
     * @return list<ProvisioningProviderEnum>
     */
    private function requiredProviders(Enrollment $enrollment): array
    {
        $required = [];
        foreach ($enrollment->provisioning_plan['providers'] ?? [] as $entry) {
            if (($entry['applicable'] ?? false) !== true) {
                continue;
            }

            $provider = ProvisioningProviderEnum::tryFrom((string) ($entry['provider'] ?? ''));
            if (! $provider) {
                continue;
            }

            $outcome = data_get($enrollment->provisioning_data, "providers.{$provider->value}.status");
            if (in_array($outcome, [
                ProvisioningOutcomeStatusEnum::FAILED->value,
                ProvisioningOutcomeStatusEnum::MANUAL_ACTION_REQUIRED->value,
                ProvisioningOutcomeStatusEnum::WAIVED->value,
            ], true)) {
                continue;
            }

            $required[] = $provider;
        }

        return $required;
    }

    private function queueOrFlagManual(Enrollment $enrollment, ProvisioningProviderEnum $provider): ?int
    {
        if (! $this->canRevoke($enrollment, $provider)) {
            $this->recordManualAttempt($enrollment, $provider);

            return null;
        }

        return $this->attempts
            ->queue($enrollment, ProvisioningTriggerEnum::REVOCATION, provider: $provider)
            ->id;
    }

    private function canRevoke(Enrollment $enrollment, ProvisioningProviderEnum $provider): bool
    {
        $references = data_get($enrollment->provisioning_data, "providers.{$provider->value}.data", []);
        if ($references === []) {
            return false;
        }

        try {
            return $this->providers->resolve($provider) instanceof RevocationProvider;
        } catch (Throwable) {
            return false;
        }
    }

    private function recordManualAttempt(Enrollment $enrollment, ProvisioningProviderEnum $provider): void
    {
        $existing = ProvisioningAttempt::query()
            ->where('enrollment_id', $enrollment->id)
            ->where('provider', $provider->value)
            ->where('trigger', ProvisioningTriggerEnum::REVOCATION->value)
            ->where('status', ProvisioningAttemptStatusEnum::MANUAL_ACTION_REQUIRED->value)
            ->exists();
        if ($existing) {
            return;
        }

        ProvisioningAttempt::query()->create([
            'enrollment_id'             => $enrollment->id,
            'provider'                  => $provider,
            'trigger'                   => ProvisioningTriggerEnum::REVOCATION,
            'status'                    => ProvisioningAttemptStatusEnum::MANUAL_ACTION_REQUIRED,
            'sequence'                  => $this->nextSequence($enrollment, $provider),
            'retryable'                 => false,
            'failure_message'           => mb_substr(__('messages.provisioning.revocation_not_supported'), 0, 1000),
            'failure_metadata'          => ['kind' => 'revocation', 'provider_supported' => false],
            'failed_at'                 => now(),
            'manual_action_required_at' => now(),
        ]);
    }

    /**
     * Derive the Enrollment revocation state from the immutable attempt log.
     * The method mutates the model; callers persist it.
     */
    private function recalculate(Enrollment $enrollment): void
    {
        if ($enrollment->isRevocationComplete()) {
            return;
        }

        $required = $this->requiredProviders($enrollment);
        if ($required === []) {
            $enrollment->revocation_status = EnrollmentRevocationStatusEnum::REVOKED;
            $enrollment->enrollment_status = EnrollmentStatusEnum::CANCELLED;

            return;
        }

        $latest = ProvisioningAttempt::query()
            ->where('enrollment_id', $enrollment->id)
            ->where('trigger', ProvisioningTriggerEnum::REVOCATION->value)
            ->orderBy('sequence')
            ->get()
            ->groupBy(fn (ProvisioningAttempt $attempt): string => $attempt->provider->value)
            ->map(fn (Collection $attempts): ProvisioningAttempt => $attempts->last());

        $pending = false;
        $failed  = false;
        $manual  = false;
        foreach ($required as $provider) {
            if ($this->providerRevoked($enrollment, $provider)) {
                continue;
            }

            $attempt = $latest->get($provider->value);
            if (! $attempt) {
                $pending = true;

                continue;
            }

            match ($attempt->status) {
                ProvisioningAttemptStatusEnum::SUCCEEDED              => null,
                ProvisioningAttemptStatusEnum::MANUAL_ACTION_REQUIRED => $manual  = true,
                ProvisioningAttemptStatusEnum::FAILED                 => $failed  = true,
                default                                               => $pending = true,
            };
        }

        if (! $pending && ! $failed && ! $manual) {
            $enrollment->revocation_status = EnrollmentRevocationStatusEnum::REVOKED;
            $enrollment->enrollment_status = EnrollmentStatusEnum::CANCELLED;

            return;
        }

        $enrollment->revocation_status = match (true) {
            $manual               => EnrollmentRevocationStatusEnum::MANUAL_ACTION_REQUIRED,
            $failed && ! $pending => EnrollmentRevocationStatusEnum::FAILED,
            default               => EnrollmentRevocationStatusEnum::PENDING,
        };
        $enrollment->enrollment_status = EnrollmentStatusEnum::SUSPENDED;
    }

    private function providerRevoked(Enrollment $enrollment, ProvisioningProviderEnum $provider): bool
    {
        $revocationSucceeded = ProvisioningAttempt::query()
            ->where('enrollment_id', $enrollment->id)
            ->where('provider', $provider->value)
            ->where('trigger', ProvisioningTriggerEnum::REVOCATION->value)
            ->where('status', ProvisioningAttemptStatusEnum::SUCCEEDED->value)
            ->exists();

        if ($revocationSucceeded) {
            return true;
        }

        // Bridge: the legacy access-reconciliation path physically removes
        // access for a cancelled Enrollment, so it settles revocation too.
        return ProvisioningAttempt::query()
            ->where('enrollment_id', $enrollment->id)
            ->where('provider', $provider->value)
            ->where('status', ProvisioningAttemptStatusEnum::SUCCEEDED->value)
            ->where('failure_metadata->kind', 'access_reconciliation')
            ->where('failure_metadata->requested_status', EnrollmentStatusEnum::CANCELLED->value)
            ->exists();
    }

    private function hasActiveAttempt(Enrollment $enrollment, ProvisioningProviderEnum $provider): bool
    {
        return ProvisioningAttempt::query()
            ->where('enrollment_id', $enrollment->id)
            ->where('provider', $provider->value)
            ->whereIn('status', [
                ProvisioningAttemptStatusEnum::QUEUED,
                ProvisioningAttemptStatusEnum::RUNNING,
                ProvisioningAttemptStatusEnum::RETRY_SCHEDULED,
            ])
            ->exists();
    }

    private function nextSequence(Enrollment $enrollment, ProvisioningProviderEnum $provider): int
    {
        return ((int) ProvisioningAttempt::query()
            ->where('enrollment_id', $enrollment->id)
            ->where('provider', $provider->value)
            ->max('sequence')) + 1;
    }

    /**
     * @param  array<string, mixed>  $references
     * @return array<string, mixed>
     */
    private function safeReferences(array $references): array
    {
        return collect($references)->only([
            'moodle_user_id', 'moodle_course_id', 'revoked_at',
        ])->all();
    }
}
