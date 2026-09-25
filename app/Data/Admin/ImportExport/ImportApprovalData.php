<?php

declare(strict_types=1);

namespace App\Data\Admin\ImportExport;

use App\Enums\ImportExport\ImportIdentityKeyEnum;
use App\Enums\ImportExport\ImportRunStatusEnum;
use App\Enums\ImportExport\SpreadsheetResourceEnum;
use Spatie\LaravelData\Data;

final class ImportApprovalData extends Data
{
    public function __construct(
        public string $run_id,
        public SpreadsheetResourceEnum $resource,
        public ImportRunStatusEnum $status,
        public ImportIdentityKeyEnum $identity_key,
        public ImportApprovalSummaryData $summary,
    ) {}
}
