<?php

declare(strict_types=1);

namespace App\Services\ImportExport;

use App\Contracts\ImportExport\ExportResourceContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;

/** @implements WithMapping<Model> */
final class ResourceSpreadsheetExport extends StringValueBinder implements FromQuery, WithCustomValueBinder, WithHeadings, WithMapping
{
    public function __construct(private readonly ExportResourceContract $resource, private readonly string $locale) {}

    /** @return Builder<covariant Model> */
    public function query(): Builder
    {
        return $this->resource->query();
    }

    public function headings(): array
    {
        return $this->resource->headings();
    }

    public function map(mixed $row): array
    {
        return $this->resource->map($row, $this->locale);
    }
}
