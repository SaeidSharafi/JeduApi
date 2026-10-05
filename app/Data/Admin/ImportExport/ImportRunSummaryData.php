<?php

declare(strict_types=1);

namespace App\Data\Admin\ImportExport;

use Spatie\LaravelData\Data;

final class ImportRunSummaryData extends Data
{
    public function __construct(
        public int $total_rows,
        public int $valid_rows,
        public int $invalid_rows,
        public int $created_count,
        public int $updated_count,
        public int $local_failure_count,
        public int $provider_success_count,
        public int $provider_failure_count,
        public int $retryable_provider_failure_count,
    ) {}
}
