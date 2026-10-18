<?php

declare(strict_types=1);

namespace App\Data\Admin\Dashboard;

use Spatie\LaravelData\Data;

/**
 * Real gateway failures in the last 24 hours.
 *
 * `rate_pct` is completed ÷ (completed + gateway-failed) and is null without a sample.
 */
final class DashboardLiveGatewayErrorsData extends Data
{
    public function __construct(
        public int $count,
        public ?float $rate_pct,
    ) {}
}
