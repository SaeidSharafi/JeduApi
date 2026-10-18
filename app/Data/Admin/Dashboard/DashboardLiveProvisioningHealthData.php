<?php

declare(strict_types=1);

namespace App\Data\Admin\Dashboard;

use Spatie\LaravelData\Data;

/**
 * Provisioning health of active enrollments.
 *
 * `healthy_share_pct` is healthy ÷ active and is null when there are no active enrollments.
 */
final class DashboardLiveProvisioningHealthData extends Data
{
    public function __construct(
        public int $healthy_count,
        public int $active_count,
        public ?float $healthy_share_pct,
        public int $open_failures,
    ) {}
}
