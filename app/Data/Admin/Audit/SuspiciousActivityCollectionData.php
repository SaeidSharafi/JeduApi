<?php

declare(strict_types=1);

namespace App\Data\Admin\Audit;

use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

final class SuspiciousActivityCollectionData extends Data
{
    public function __construct(
        public Collection $rapid_succession,
        public Collection $unusual_admin_activity,
        public ?Collection $large_transactions = null,
        public ?Collection $off_hours_transactions = null,
        public ?Collection $high_frequency_users = null,
        public ?Collection $round_number_patterns = null,

    ) {}
}
