<?php

declare(strict_types=1);

namespace App\Data\Admin\Dashboard;

use Spatie\LaravelData\Data;

/** Spreadsheet imports currently being processed. */
final class DashboardLiveImportsHealthData extends Data
{
    public function __construct(public int $running) {}
}
