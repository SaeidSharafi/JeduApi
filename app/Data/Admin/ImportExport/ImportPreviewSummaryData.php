<?php

declare(strict_types=1);

namespace App\Data\Admin\ImportExport;

use Spatie\LaravelData\Data;

/**
 * Preview counters. Post-approval runs use their own counter names.
 */
final class ImportPreviewSummaryData extends Data
{
    public function __construct(
        public int $total_rows,
        public int $valid_rows,
        public int $invalid_rows,
        public int $create_count,
        public int $update_count,
        public int $provider_provisioning_request_count,
    ) {}
}
