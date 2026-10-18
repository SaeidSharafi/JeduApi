<?php

declare(strict_types=1);

namespace App\Data\Admin\Dashboard;

use Spatie\LaravelData\Data;

/**
 * Gateway success rate over the last 24 hours.
 *
 * `sample` is completed plus gateway-failed attempts; `rate_pct` is null without a sample.
 */
final class DashboardLiveGatewayHealthData extends Data
{
    public function __construct(
        public ?float $rate_pct,
        public int $sample,
    ) {}
}
