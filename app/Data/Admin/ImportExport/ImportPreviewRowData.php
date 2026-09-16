<?php

declare(strict_types=1);

namespace App\Data\Admin\ImportExport;

use App\Enums\ImportExport\ImportRowActionEnum;
use App\Enums\ImportExport\ImportRowStatusEnum;
use Spatie\LaravelData\Data;

/**
 * One preview row. Lifecycle keys are engine owned; resource values live under data.
 */
final class ImportPreviewRowData extends Data
{
    /**
     * @param  list<ImportRowErrorData>  $errors
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public int $row_number,
        public ImportRowStatusEnum $status,
        public ?ImportRowActionEnum $operation,
        public array $data,
        public array $errors,
    ) {}
}
