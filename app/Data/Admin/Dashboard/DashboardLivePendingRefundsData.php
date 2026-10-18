<?php

declare(strict_types=1);

namespace App\Data\Admin\Dashboard;

use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Data;

/** Refunds waiting for the finance team, with their total and oldest age. */
final class DashboardLivePendingRefundsData extends Data
{
    public function __construct(
        public int $count,
        public int $amount,
        public ?Verta $oldest_created_at,
    ) {}
}
