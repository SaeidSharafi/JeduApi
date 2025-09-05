<?php

declare(strict_types=1);

namespace App\Data\Admin\Audit;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Support\Validation\ValidationContext;

class SuspiciousActivityCollectionData extends Data
{
    public function __construct(
        public Collection $rapid_succession,
        public Collection $unusual_admin_activity,
        public ?Collection $large_transactions = null,
        public ?Collection $off_hours_transactions = null,
        public ?Collection $high_frequency_users = null,
        public ?Collection $round_number_patterns = null,



    ) {
    }
}
