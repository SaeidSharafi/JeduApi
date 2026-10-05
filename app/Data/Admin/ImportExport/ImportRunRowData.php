<?php

declare(strict_types=1);

namespace App\Data\Admin\ImportExport;

use Spatie\LaravelData\Data;

final class ImportRunRowData extends Data
{
    /** @param array<string, mixed> $data @param array<string, array{status: string, message: ?string}> $providers @param list<array{field: string, code: string, message: string}> $errors */
    public function __construct(
        public int $row_number,
        public string $status,
        public ?string $operation,
        public array $data,
        public array $providers,
        public array $errors,
    ) {}
}
