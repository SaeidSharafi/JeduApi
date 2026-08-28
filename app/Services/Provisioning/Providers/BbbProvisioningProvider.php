<?php

declare(strict_types=1);

namespace App\Services\Provisioning\Providers;

use App\Contracts\Provisioning\ProvisioningProvider;
use App\Enums\ProvisioningProviderEnum;
use App\Exceptions\Integrations\UnrecoverableProvisioningException;
use App\Models\Enrollment;
use App\Services\Integrations\BbbService;

final readonly class BbbProvisioningProvider implements ProvisioningProvider
{
    public function __construct(private BbbService $bbb) {}

    public function provider(): ProvisioningProviderEnum
    {
        return ProvisioningProviderEnum::BBB;
    }

    public function provision(Enrollment $enrollment): array
    {
        if (! $this->isApplicable($enrollment)) {
            throw new UnrecoverableProvisioningException('BBB provider is not applicable to this enrollment.');
        }
        if (! $this->bbb->isEnabled()) {
            throw new UnrecoverableProvisioningException('BBB provider is disabled.');
        }

        $this->bbb->assertConfigured();
        $details   = $enrollment->productDeliveryOption?->details_json ?? [];
        $meetingId = data_get($details, 'nili_room_id', data_get($details, 'meeting_id'));
        if (! is_string($meetingId) || mb_trim($meetingId) === '') {
            throw new UnrecoverableProvisioningException(
                __('messages.provisioning.bbb_meeting_id_missing')
            );
        }

        return ['meeting_id' => $meetingId];
    }

    private function isApplicable(Enrollment $enrollment): bool
    {
        return collect($enrollment->provisioning_plan['providers'] ?? [])->contains(
            fn (array $provider): bool => ($provider['provider'] ?? null) === $this->provider()->value
                && ($provider['applicable'] ?? false)                     === true
                && ($provider['readiness'] ?? null)                       === 'ready',
        );
    }
}
