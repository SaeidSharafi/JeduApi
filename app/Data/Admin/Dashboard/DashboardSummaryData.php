<?php

declare(strict_types=1);

namespace App\Data\Admin\Dashboard;

use Spatie\LaravelData\Data;

/** The summary block: the resolved period, five KPIs and the daily trend. */
final class DashboardSummaryData extends Data
{
    public function __construct(
        public DashboardSummaryPeriodData $period,
        public DashboardSummaryKpisData $kpis,
        public DashboardSummaryTrendData $trend,
    ) {}
}
