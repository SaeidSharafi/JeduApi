<?php

declare(strict_types=1);

namespace App\Services\Provisioning;

use App\Contracts\Provisioning\ProvisioningProvider;
use App\Enums\ProvisioningProviderEnum;
use App\Services\Provisioning\Providers\ImsProvisioningProvider;
use App\Services\Provisioning\Providers\MoodleProvisioningProvider;
use InvalidArgumentException;

final readonly class ProvisioningProviderRegistry
{
    public function __construct(private MoodleProvisioningProvider $moodle, private ImsProvisioningProvider $ims) {}

    public function resolve(ProvisioningProviderEnum $provider): ProvisioningProvider
    {
        return match ($provider) {
            ProvisioningProviderEnum::MOODLE => $this->moodle,
            ProvisioningProviderEnum::IMS    => $this->ims,
            default                          => throw new InvalidArgumentException("Provider [{$provider->value}] has no adapter yet."),
        };
    }
}
