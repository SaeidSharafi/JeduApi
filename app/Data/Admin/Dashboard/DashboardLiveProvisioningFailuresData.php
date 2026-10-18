<?php

declare(strict_types=1);

namespace App\Data\Admin\Dashboard;

use Spatie\LaravelData\Data;

/** Enrollments whose latest attempt per provider is failed or needs manual action. */
final class DashboardLiveProvisioningFailuresData extends Data
{
    public function __construct(public int $count) {}
}
