<?php

declare(strict_types=1);

namespace App\Data\Admin\Dashboard;

use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Data;

/** Orders still in processing after 48 hours, with the oldest update time. */
final class DashboardLiveStuckProcessingData extends Data
{
    public function __construct(
        public int $count,
        public ?Verta $oldest_updated_at,
    ) {}
}
