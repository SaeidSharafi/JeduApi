<?php

declare(strict_types=1);

namespace App\Data\Admin\Dashboard;

use Spatie\LaravelData\Data;

/** Settlement mix of the paid-order cohort: fully settled versus Prepayment deposit orders. */
final class DashboardPaymentMixData extends Data
{
    public function __construct(
        public DashboardMixBucketData $full,
        public DashboardMixBucketData $deposit,
    ) {}
}
