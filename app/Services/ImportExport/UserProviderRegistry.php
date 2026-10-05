<?php

declare(strict_types=1);

namespace App\Services\ImportExport;

use App\Contracts\ImportExport\UserProvisioningProvider;
use App\Enums\ImportExport\UserProvisioningProviderEnum;
use App\Services\ImportExport\Providers\ImsUserProvider;
use App\Services\ImportExport\Providers\MoodleUserProvider;
use App\Services\ImportExport\Providers\NiliroomUserProvider;
use App\Services\ImportExport\Providers\SkyroomUserProvider;
use Illuminate\Contracts\Container\Container;

final readonly class UserProviderRegistry
{
    public function __construct(private Container $container) {}

    public function resolve(UserProvisioningProviderEnum $provider): UserProvisioningProvider
    {
        return $this->container->make(match ($provider) {
            UserProvisioningProviderEnum::MOODLE   => MoodleUserProvider::class,
            UserProvisioningProviderEnum::IMS      => ImsUserProvider::class,
            UserProvisioningProviderEnum::NILIROOM => NiliroomUserProvider::class,
            UserProvisioningProviderEnum::SKYROOM  => SkyroomUserProvider::class,
        });
    }
}
