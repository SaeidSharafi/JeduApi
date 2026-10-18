<?php

declare(strict_types=1);

namespace App\Data\Admin\Dashboard;

use Spatie\LaravelData\Data;

/** One ranked row of the dashboard rankings block. */
final class DashboardRankingRowData extends Data
{
    public function __construct(
        public string $name,
        public int $orders,
        public int $revenue,
        public float $share_pct,
    ) {}
}
