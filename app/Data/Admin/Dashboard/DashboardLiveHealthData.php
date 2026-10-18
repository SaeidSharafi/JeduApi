<?php

declare(strict_types=1);

namespace App\Data\Admin\Dashboard;

use Spatie\LaravelData\Data;

/** System health signals that are not financial. */
final class DashboardLiveHealthData extends Data
{
    public function __construct(
        public DashboardLiveGatewayHealthData $gateway,
        public DashboardLiveProvisioningHealthData $provisioning,
        public DashboardLiveSmsHealthData $sms,
        public DashboardLiveImportsHealthData $imports,
    ) {}
}
