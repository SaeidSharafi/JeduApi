<?php

declare(strict_types=1);

namespace App\Data\Admin\Dashboard;

use Spatie\LaravelData\Data;

/** Pending payment attempts: the KPI plus the money they would bring in. */
final class DashboardSummaryUnfinishedPaymentsData extends Data
{
    public function __construct(
        public int $value,
        public int $previous,
        public ?float $change_pct,
        public int $amount,
        public int $previous_amount,
    ) {}
}
