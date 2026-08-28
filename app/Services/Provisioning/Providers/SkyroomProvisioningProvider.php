<?php

declare(strict_types=1);

namespace App\Services\Provisioning\Providers;

use App\Contracts\Provisioning\ProvisioningProvider;
use App\Enums\ProvisioningProviderEnum;
use App\Exceptions\Integrations\UnrecoverableProvisioningException;
use App\Models\Enrollment;
use App\Services\Integrations\SkyroomService;

final readonly class SkyroomProvisioningProvider implements ProvisioningProvider
{
    public function __construct(private SkyroomService $skyroom) {}

    public function provider(): ProvisioningProviderEnum
    {
        return ProvisioningProviderEnum::SKYROOM;
    }

    public function supportsAccessReconciliation(): bool
    {
        return false;
    }

    public function provision(Enrollment $enrollment): array
    {
        if (! $this->isApplicable($enrollment)) {
            throw new UnrecoverableProvisioningException('Skyroom provider is not applicable to this enrollment.');
        }
        if (! $this->skyroom->isEnabled()) {
            throw new UnrecoverableProvisioningException('Skyroom provider is disabled.');
        }

        $this->skyroom->assertConfigured();
        $roomId = data_get($enrollment->productDeliveryOption?->details_json, 'room_id');
        if (! is_int($roomId) && ! (is_string($roomId) && ctype_digit($roomId))) {
            throw new UnrecoverableProvisioningException(
                __('messages.provisioning.skyroom_room_id_missing')
            );
        }
        $roomId = (int) $roomId;
        if ($roomId < 1) {
            throw new UnrecoverableProvisioningException(
                __('messages.provisioning.skyroom_room_id_missing')
            );
        }

        $result        = $this->skyroom->findOrCreateUser($enrollment->customer);
        $skyroomUserId = $result['skyroom_user_id'] ?? null;
        if (! is_int($skyroomUserId) && ! (is_numeric($skyroomUserId) && (int) $skyroomUserId > 0)) {
            throw new UnrecoverableProvisioningException('Skyroom user reference is invalid.');
        }
        $skyroomUserId = (int) $skyroomUserId;
        $this->skyroom->addUserToRoom($roomId, $skyroomUserId);

        return [
            'room_id'         => $roomId,
            'skyroom_user_id' => $skyroomUserId,
        ];
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
