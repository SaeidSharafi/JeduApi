<?php

declare(strict_types=1);

namespace App\Data\Admin\Dashboard;

use Spatie\LaravelData\Data;

/** Live operations queue and system health: a snapshot of now and the last 24 hours. */
final class DashboardLiveData extends Data
{
    public function __construct(
        public DashboardLiveOpsData $ops,
        public DashboardLiveHealthData $health,
    ) {}
}
