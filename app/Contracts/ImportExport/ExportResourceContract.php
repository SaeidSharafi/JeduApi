<?php

declare(strict_types=1);

namespace App\Contracts\ImportExport;

use App\Enums\ImportExport\SpreadsheetResourceEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

interface ExportResourceContract
{
    public function resource(): SpreadsheetResourceEnum;

    public function authorize(): void;

    /** @return Builder<covariant Model> */
    public function query(): Builder;

    /** @return list<string> */
    public function headings(): array;

    /** @return list<string|int|null> */
    public function map(Model $row, string $locale): array;
}
