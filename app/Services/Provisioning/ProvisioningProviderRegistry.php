<?php

declare(strict_types=1);

namespace App\Services\Provisioning;

use App\Contracts\Provisioning\ProvisioningProvider;
use App\Enums\ProvisioningProviderEnum;
use App\Services\Provisioning\Providers\MoodleProvisioningProvider;
use InvalidArgumentException;

final readonly class ProvisioningProviderRegistry
{
    public function __construct(private MoodleProvisioningProvider $moodle) {}

    public function resolve(ProvisioningProviderEnum $provider): ProvisioningProvider
    {
        return match ($provider) {
            ProvisioningProviderEnum::MOODLE => $this->moodle,
            default                          => throw new InvalidArgumentException("Provider [{$provider->value}] has no adapter yet."),
        };
    }
}
