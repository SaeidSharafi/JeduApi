<?php

declare(strict_types=1);

namespace App\Data\Admin\ImportExport;

use App\Enums\ImportExport\ImportIdentityKeyEnum;
use App\Enums\ImportExport\ImportRunStatusEnum;
use App\Enums\ImportExport\SpreadsheetResourceEnum;
use Spatie\LaravelData\Data;

/**
 * Stable import preview envelope shared by every resource.
 */
final class ImportPreviewData extends Data
{
    /**
     * @param  list<ImportPreviewRowData>  $rows
     */
    public function __construct(
        public string $run_id,
        public SpreadsheetResourceEnum $resource,
        public ImportRunStatusEnum $status,
        public ImportIdentityKeyEnum $identity_key,
        public ImportPreviewSummaryData $summary,
        public array $rows,
        public bool $can_approve,
        public string $approval_warning,
    ) {}
}
