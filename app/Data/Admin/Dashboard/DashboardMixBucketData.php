<?php

declare(strict_types=1);

namespace App\Data\Admin\Dashboard;

use Spatie\LaravelData\Data;

/** One bucket of the money mixes: order count, IRR amount and its one-decimal share. */
final class DashboardMixBucketData extends Data
{
    public function __construct(
        public int $orders,
        public int $amount,
        public float $share_pct,
    ) {}
}
