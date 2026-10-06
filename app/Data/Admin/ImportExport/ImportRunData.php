<?php

declare(strict_types=1);

namespace App\Data\Admin\ImportExport;

use App\Enums\ImportExport\ImportIdentityKeyEnum;
use App\Enums\ImportExport\ImportRunStatusEnum;
use App\Enums\ImportExport\SpreadsheetResourceEnum;
use Spatie\LaravelData\Data;

final class ImportRunData extends Data
{
    /**
     * @param  list<ImportRunRowData>  $rows
     * @param  array{available: bool, download_url: ?string}  $error_report
     */
    public function __construct(
        public string $run_id,
        public SpreadsheetResourceEnum $resource,
        public ImportRunStatusEnum $status,
        public ?ImportIdentityKeyEnum $identity_key,
        public ImportRunSummaryData $summary,
        public array $rows,
        public array $error_report = ['available' => false, 'download_url' => null],
    ) {}
}
