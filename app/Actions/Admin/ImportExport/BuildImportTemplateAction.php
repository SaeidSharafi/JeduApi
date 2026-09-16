<?php

declare(strict_types=1);

namespace App\Actions\Admin\ImportExport;

use App\Services\ImportExport\ImportTemplateExport;
use App\Services\ImportExport\SpreadsheetResourceRegistry;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Generates the official import template from the resource column contract.
 */
final readonly class BuildImportTemplateAction
{
    public function __construct(private SpreadsheetResourceRegistry $registry) {}

    public function handle(string $resource): BinaryFileResponse
    {
        $contract = $this->registry->import($resource);

        return Excel::download(
            new ImportTemplateExport($contract),
            $contract->resource()->value.'-import-template.xlsx',
            ExcelWriter::XLSX,
        );
    }
}
