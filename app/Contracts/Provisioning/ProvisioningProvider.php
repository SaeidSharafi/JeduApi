<?php

declare(strict_types=1);

namespace App\Contracts\Provisioning;

use App\Enums\ProvisioningProviderEnum;
use App\Models\Enrollment;

interface ProvisioningProvider
{
    public function provider(): ProvisioningProviderEnum;

    /** @return array<string, mixed> Canonical safe references only. */
    public function provision(Enrollment $enrollment): array;
}
