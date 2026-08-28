<?php

declare(strict_types=1);

namespace App\Data\Admin\Enrollment;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

final class AdvancedProvisioningDiagnosticsData extends Data
{
    public function __construct(
        public ProvisioningDiagnosticsData $summary,
        #[DataCollectionOf(ProvisioningAttemptDiagnosticData::class)]
        public DataCollection $attempts,
    ) {}
}
