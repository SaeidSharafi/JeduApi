<?php

declare(strict_types=1);

namespace App\Data\Admin\Dashboard;

use Spatie\LaravelData\Data;

/** One count or money KPI with its previous-window value and one-decimal change. */
final class DashboardSummaryKpiData extends Data
{
    public function __construct(
        public int $value,
        public int $previous,
        public ?float $change_pct,
    ) {}
}
