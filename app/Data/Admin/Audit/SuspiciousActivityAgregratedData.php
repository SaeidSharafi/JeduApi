<?php

declare(strict_types=1);

namespace App\Data\Admin\Audit;

use Carbon\Carbon;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Support\Validation\ValidationContext;

class SuspiciousActivityAgregratedData extends Data
{
    public function __construct(
        public array $detection_period,
        public array $detection_criteria,
        public SuspiciousActivityCollectionData $suspicious_activities,
        public array $summary = [],
    ) {}
}
