<?php

declare(strict_types=1);

namespace App\Jobs\Provisioning\Concerns;

use App\Enums\EnrollmentStatusEnum;
use App\Enums\ProvisioningStatusEnum;
use App\Models\Enrollment;

trait HandlesProvisioningStatus
{
    /**
     * @param  array<string, mixed>  $data
     */
    protected function markProvisioningSuccess(
        Enrollment $enrollment,
        string $provider,
        array $data,
        ?int $externalEnrollmentId = null
    ): void {
        $provisioningData = $enrollment->provisioning_data ?? [];
        $providersData    = $provisioningData['providers'] ?? [];

        $providersData[$provider] = [
            'status'         => 'success',
            'provisioned_at' => now()->toDateTimeString(),
            'data'           => $data,
        ];

        $provisioningData['providers'] = $providersData;

        if ($externalEnrollmentId !== null) {
            $enrollment->external_enrollment_id = $externalEnrollmentId;
        }

        $requiredProviders = $this->requiredProviders($enrollment);
        $allSuccessful     = empty($requiredProviders) || collect($requiredProviders)
            ->every(fn (string $requiredProvider): bool => ($providersData[$requiredProvider]['status'] ?? null) === 'success');

        if ($allSuccessful) {
            $enrollment->enrollment_status = EnrollmentStatusEnum::ACTIVE;
        }

        $enrollment->provisioning_data   = $provisioningData;
        $enrollment->provisioning_status = $this->aggregateStatus($enrollment, $providersData)->value;
        $enrollment->save();
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function markProvisioningFailure(Enrollment $enrollment, string $provider, string $error, array $metadata = []): void
    {
        $provisioningData = $enrollment->provisioning_data ?? [];
        $providersData    = $provisioningData['providers'] ?? [];

        $providersData[$provider] = [
            'status'     => 'failed',
            'failed_at'  => now()->toDateTimeString(),
            'last_error' => $error,
            'metadata'   => $metadata,
        ];

        $provisioningData['providers']   = $providersData;
        $enrollment->provisioning_data   = $provisioningData;
        $enrollment->enrollment_status   = EnrollmentStatusEnum::PROVISIONING_FAILED;
        $enrollment->provisioning_status = $this->aggregateStatus($enrollment, $providersData)->value;
        $enrollment->save();
    }

    /**
     * @return array<int, string>
     */
    private function requiredProviders(Enrollment $enrollment): array
    {
        return collect($enrollment->provisioning_plan['providers'] ?? [])
            ->pluck('provider')
            ->values()
            ->all();
    }

    private function aggregateStatus(Enrollment $enrollment, array $providersData): ProvisioningStatusEnum
    {
        $plannedProviders = $enrollment->provisioning_plan['providers'] ?? [];
        if ($plannedProviders === []) {
            return ProvisioningStatusEnum::HEALTHY;
        }

        if (collect($plannedProviders)->contains(fn (array $provider): bool => $provider['readiness'] !== 'ready')) {
            return ProvisioningStatusEnum::MANUAL_ACTION_REQUIRED;
        }

        if (collect($providersData)->contains(fn (array $provider): bool => ($provider['status'] ?? null) === 'failed')) {
            return ProvisioningStatusEnum::DEGRADED;
        }

        if (collect($plannedProviders)->every(fn (array $provider): bool => ($providersData[$provider['provider']]['status'] ?? null) === 'success')) {
            return ProvisioningStatusEnum::HEALTHY;
        }

        return ProvisioningStatusEnum::IN_PROGRESS;
    }
}
