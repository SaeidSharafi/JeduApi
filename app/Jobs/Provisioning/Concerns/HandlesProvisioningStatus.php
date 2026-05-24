<?php

declare(strict_types=1);

namespace App\Jobs\Provisioning\Concerns;

use App\Enums\EnrollmentStatusEnum;
use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\System\SettingKeyEnum;
use App\Models\Enrollment;
use App\Services\SettingsService;

trait HandlesProvisioningStatus
{
    private function markProvisioningSuccess(
        Enrollment $enrollment,
        string $provider,
        array $data,
        ?string $externalEnrollmentId = null
    ): void {
        $provisioningData = $enrollment->provisioning_data ?? [];
        $providersData    = $provisioningData['providers'] ?? [];

        $providersData[$provider] = [
            'status'         => 'success',
            'provisioned_at' => now()->toDateTimeString(),
            'data'           => $data,
        ];

        $provisioningData['providers'] = $providersData;

        if ($externalEnrollmentId !== null && $externalEnrollmentId !== '') {
            $enrollment->external_enrollment_id = $externalEnrollmentId;
        }

        $requiredProviders = $this->requiredProviders($enrollment);
        $allSuccessful     = collect($requiredProviders)
            ->every(fn (string $requiredProvider): bool => ($providersData[$requiredProvider]['status'] ?? null) === 'success');

        if ($allSuccessful) {
            $enrollment->enrollment_status = EnrollmentStatusEnum::ACTIVE;
        }

        $enrollment->provisioning_data = $provisioningData;
        $enrollment->save();
    }

    private function markProvisioningFailure(Enrollment $enrollment, string $provider, string $error): void
    {
        $provisioningData = $enrollment->provisioning_data ?? [];
        $providersData    = $provisioningData['providers'] ?? [];

        $providersData[$provider] = [
            'status'     => 'failed',
            'failed_at'  => now()->toDateTimeString(),
            'last_error' => $error,
        ];

        $provisioningData['providers'] = $providersData;
        $enrollment->provisioning_data = $provisioningData;
        $enrollment->enrollment_status = EnrollmentStatusEnum::PROVISIONING_FAILED;
        $enrollment->save();
    }

    /**
     * @return array<int, string>
     */
    private function requiredProviders(Enrollment $enrollment): array
    {
        $isImsActive    = data_get(app(SettingsService::class)->get(SettingKeyEnum::IMS), 'enabled', false);
        $providers      = $isImsActive ? ['ims'] : [];
        $deliveryMethod = $enrollment->productDeliveryOption?->delivery_method;

        if ($deliveryMethod === DeliveryMethodEnum::LMS_MOODLE) {
            $providers[] = 'moodle';
        }

        if ($deliveryMethod === DeliveryMethodEnum::VIDEO_PLATFORM_SPOTPLAYER) {
            $providers[] = 'spotplayer';
        }

        if ($deliveryMethod === DeliveryMethodEnum::LIVE_SESSION_BBB) {
            $providers[] = 'bbb';
        }

        return $providers;
    }
}
