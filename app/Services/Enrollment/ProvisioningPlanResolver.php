<?php

declare(strict_types=1);

namespace App\Services\Enrollment;

use App\Contracts\Integrations\BbbClientContract;
use App\Contracts\Integrations\ImsClientContract;
use App\Contracts\Integrations\MoodleClientContract;
use App\Contracts\Integrations\SkyroomClientContract;
use App\Contracts\Integrations\SpotPlayerClientContract;
use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\ProvisioningProviderEnum;
use App\Enums\ProvisioningReadinessEnum;
use App\Enums\ProvisioningStatusEnum;
use App\Models\ProductDeliveryOption;
use App\Services\Integrations\AbstractIntegrationService;

final readonly class ProvisioningPlanResolver
{
    public function __construct(
        private ImsClientContract $ims,
        private MoodleClientContract $moodle,
        private SpotPlayerClientContract $spotPlayer,
        private BbbClientContract $bbb,
        private SkyroomClientContract $skyroom,
    ) {}

    /**
     * @return array{version: int, providers: array<int, array{provider: string, applicable: bool, readiness: string, configuration_issue: ?string}>, status: string, resolved_at: string}
     */
    public function resolve(ProductDeliveryOption $deliveryOption): array
    {
        $details   = $deliveryOption->details_json ?? [];
        $providers = [];

        if (is_string($details['ims_course_code'] ?? null) && $details['ims_course_code'] !== '') {
            $providers[] = $this->provider(ProvisioningProviderEnum::IMS, $this->ims);
        }

        $deliveryProvider = match ($deliveryOption->delivery_method) {
            DeliveryMethodEnum::LMS_MOODLE                => [ProvisioningProviderEnum::MOODLE, $this->moodle],
            DeliveryMethodEnum::VIDEO_PLATFORM_SPOTPLAYER => [ProvisioningProviderEnum::SPOTPLAYER, $this->spotPlayer],
            DeliveryMethodEnum::LIVE_SESSION_BBB          => [ProvisioningProviderEnum::BBB, $this->bbb],
            DeliveryMethodEnum::LIVE_SESSION_SKYROOM      => [ProvisioningProviderEnum::SKYROOM, $this->skyroom],
            default                                       => null,
        };

        if ($deliveryProvider !== null) {
            [$provider, $service] = $deliveryProvider;
            $providers[]          = $this->provider($provider, $service);
        }

        if ($deliveryOption->delivery_method !== DeliveryMethodEnum::LMS_MOODLE
            && is_numeric($details['moodle_quiz_course_id'] ?? null)
        ) {
            $providers[] = $this->provider(ProvisioningProviderEnum::MOODLE_QUIZ, $this->moodle);
        }

        $status = $providers === []
            ? ProvisioningStatusEnum::HEALTHY
            : (collect($providers)->contains(fn (array $provider): bool => $provider['readiness'] !== ProvisioningReadinessEnum::READY->value)
                ? ProvisioningStatusEnum::MANUAL_ACTION_REQUIRED
                : ProvisioningStatusEnum::READY);

        return [
            'version'     => 1,
            'providers'   => $providers,
            'status'      => $status->value,
            'resolved_at' => now()->toISOString(),
        ];
    }

    /**
     * @return array{provider: string, applicable: bool, readiness: string, configuration_issue: ?string}
     */
    private function provider(ProvisioningProviderEnum $provider, AbstractIntegrationService|BbbClientContract|ImsClientContract|MoodleClientContract|SkyroomClientContract|SpotPlayerClientContract $service): array
    {
        $readiness = ! $service->isEnabled()
            ? ProvisioningReadinessEnum::DISABLED
            : (! $service->isReady() ? ProvisioningReadinessEnum::INVALID : ProvisioningReadinessEnum::READY);

        return [
            'provider'            => $provider->value,
            'applicable'          => true,
            'readiness'           => $readiness->value,
            'configuration_issue' => $readiness === ProvisioningReadinessEnum::READY ? null : 'provider_'.$readiness->value,
        ];
    }
}
