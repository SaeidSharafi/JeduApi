<?php

declare(strict_types=1);

namespace App\Data\Admin\Dashboard;

use Spatie\LaravelData\Data;

/** The operational queues an admin acts on right now. */
final class DashboardLiveOpsData extends Data
{
    public function __construct(
        public DashboardLiveGatewayErrorsData $gateway_errors,
        public DashboardLiveProvisioningFailuresData $provisioning_failures,
        public DashboardLivePendingRefundsData $pending_refunds,
        public DashboardLiveStuckProcessingData $stuck_processing,
    ) {}
}
