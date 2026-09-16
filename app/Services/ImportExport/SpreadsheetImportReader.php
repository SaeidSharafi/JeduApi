<?php

declare(strict_types=1);

namespace App\Services\ImportExport;

use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Concerns\Import;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithLimit;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Reads the first worksheet of an uploaded spreadsheet into raw rows.
 *
 * Only the shape the downloadable template defines is supported: one worksheet
 * with the first row as heading row and data below it. Nothing is repaired or
 * detected, the engine simply resolves the headings it is given. The read is
 * capped one row above the allowed data rows, so an oversized file is rejected
 * without being read into memory.
 */
final class SpreadsheetImportReader implements Import, ToArray, WithLimit
{
    public const int MAX_DATA_ROWS = 2000;

    /**
     * @return array<int, array<int, mixed>> Rows of the first worksheet, heading row first.
     */
    public function read(UploadedFile $file): array
    {
        $worksheets = Excel::toArray($this, $file, null, ExcelWriter::XLSX);

        return array_values($worksheets)[0] ?? [];
    }

    /**
     * One heading row, the allowed data rows and one sentinel row that proves
     * the file carries more data than the engine accepts.
     */
    public function limit(): int
    {
        return self::MAX_DATA_ROWS + 2;
    }

    /**
     * @param  array<array-key, mixed>  $array
     */
    public function array(array $array): void
    {
        // Rows are collected by Excel::toArray(); nothing to do here.
    }
}
