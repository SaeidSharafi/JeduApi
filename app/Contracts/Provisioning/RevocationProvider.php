<?php

declare(strict_types=1);

namespace App\Contracts\Provisioning;

use App\Models\Enrollment;

/**
 * Implemented by provisioning adapters whose provider exposes a real API for
 * removing access. Adapters that do not implement this contract cannot be
 * revoked automatically; the refund flow records their Enrollment as explicit
 * manual work instead.
 */
interface RevocationProvider
{
    /**
     * Remove every external provider access granted for the Enrollment.
     *
     * @return array<string, mixed> Canonical safe references only.
     */
    public function revoke(Enrollment $enrollment): array;
}
