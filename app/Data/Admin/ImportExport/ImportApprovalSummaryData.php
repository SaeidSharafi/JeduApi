<?php

declare(strict_types=1);

namespace App\Data\Admin\ImportExport;

use Spatie\LaravelData\Data;

final class ImportApprovalSummaryData extends Data
{
    public function __construct(
        public int $created_count,
        public int $updated_count,
        public int $provider_queued_count,
    ) {}
}
