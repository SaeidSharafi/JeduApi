<?php

declare(strict_types=1);

namespace App\Data\Admin\Dashboard;

use Spatie\LaravelData\Data;

/**
 * SMS delivery success rate over the last 24 hours.
 *
 * `sample` counts every non-skipped send; skipped sends (`status = 0`) are reported
 * separately and never lower the rate. `rate_pct` is null without a sample.
 */
final class DashboardLiveSmsHealthData extends Data
{
    public function __construct(
        public ?float $rate_pct,
        public int $sample,
        public int $skipped,
    ) {}
}
