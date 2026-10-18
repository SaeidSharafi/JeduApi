<?php

declare(strict_types=1);

namespace App\Data\Admin\Dashboard;

use Spatie\LaravelData\Data;

/** The five headline KPIs of a dashboard period. */
final class DashboardSummaryKpisData extends Data
{
    public function __construct(
        public DashboardSummaryKpiData $net_revenue,
        public DashboardSummaryKpiData $paid_orders,
        public DashboardSummaryKpiData $aov,
        public DashboardSummaryKpiData $new_learners,
        public DashboardSummaryUnfinishedPaymentsData $unfinished_payments,
    ) {}
}
