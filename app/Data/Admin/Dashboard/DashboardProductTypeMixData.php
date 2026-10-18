<?php

declare(strict_types=1);

namespace App\Data\Admin\Dashboard;

use Spatie\LaravelData\Data;

/** Product-type mix of the paid-order cohort; every bucket is always present. */
final class DashboardProductTypeMixData extends Data
{
    public function __construct(
        public DashboardMixBucketData $course,
        public DashboardMixBucketData $bundle,
        public DashboardMixBucketData $seminar,
        public DashboardMixBucketData $digital_asset,
        public DashboardMixBucketData $unknown,
    ) {}
}
