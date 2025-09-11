<?php

declare(strict_types=1);

namespace App\Data\Admin\Audit;

use Spatie\LaravelData\Data;

final class SuspiciousActivityAgregratedData extends Data
{
    public function __construct(
        public array $detection_period,
        public array $detection_criteria,
        public SuspiciousActivityCollectionData $suspicious_activities,
        public array $summary = [],
    ) {}
}
