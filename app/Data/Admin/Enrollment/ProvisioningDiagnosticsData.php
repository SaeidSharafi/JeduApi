<?php

declare(strict_types=1);

namespace App\Data\Admin\Enrollment;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

final class ProvisioningDiagnosticsData extends Data
{
    public function __construct(
        public string $status,
        #[DataCollectionOf(ProvisioningDiagnosticData::class)]
        public DataCollection $providers,
        public ?string $reconciliation_status = null,
    ) {}
}
