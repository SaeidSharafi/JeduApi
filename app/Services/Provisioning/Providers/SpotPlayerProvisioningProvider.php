<?php

declare(strict_types=1);

namespace App\Services\Provisioning\Providers;

use App\Contracts\Integrations\SpotPlayerClientContract;
use App\Contracts\Provisioning\ProvisioningProvider;
use App\Enums\ProvisioningProviderEnum;
use App\Exceptions\Integrations\RecoverableProvisioningException;
use App\Exceptions\Integrations\UnrecoverableProvisioningException;
use App\Models\Enrollment;

final readonly class SpotPlayerProvisioningProvider implements ProvisioningProvider
{
    public function __construct(private SpotPlayerClientContract $spotPlayer) {}

    public function provider(): ProvisioningProviderEnum
    {
        return ProvisioningProviderEnum::SPOTPLAYER;
    }

    public function supportsAccessReconciliation(): bool
    {
        return false;
    }

    public function provision(Enrollment $enrollment): array
    {
        if (! $this->spotPlayer->isEnabled()) {
            throw new UnrecoverableProvisioningException('SpotPlayer provider is disabled.');
        }

        $this->spotPlayer->assertConfigured();
        $enrollment = $enrollment->fresh(['customer', 'productDeliveryOption']);
        if (! $enrollment || ! $this->isApplicable($enrollment)) {
            throw new UnrecoverableProvisioningException('SpotPlayer provider is not applicable to this enrollment.');
        }
        $spotId = data_get($enrollment->productDeliveryOption?->details_json, 'spot_id');
        if (! is_string($spotId) || $spotId === '') {
            throw new UnrecoverableProvisioningException(__('messages.provisioning.spotplayer_spot_id_missing'));
        }

        try {
            $result = $this->spotPlayer->issueLicense($spotId, $enrollment->customer);
        } catch (RecoverableProvisioningException $exception) {
            throw new UnrecoverableProvisioningException(
                'SpotPlayer licence outcome is ambiguous; manual verification required.',
                0,
                $exception,
                array_merge($exception->metaData, ['ambiguous_outcome' => true]),
            );
        }

        return [
            'spot_id'     => $spotId,
            'license_key' => data_get($result, 'license_key'),
            'player_url'  => data_get($result, 'player_url'),
        ];
    }

    private function isApplicable(Enrollment $enrollment): bool
    {
        return collect($enrollment->provisioning_plan['providers'] ?? [])->contains(
            fn (array $provider): bool => ($provider['provider'] ?? null) === $this->provider()->value
                && ($provider['applicable'] ?? false)                     === true,
        );
    }
}
