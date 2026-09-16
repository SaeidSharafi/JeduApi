<?php

declare(strict_types=1);

namespace App\Data\Admin\ImportExport;

use Spatie\LaravelData\Data;

/**
 * One row level import error. The code is stable for frontend behavior, the
 * message is displayable text and the field is the canonical spreadsheet field.
 */
final class ImportRowErrorData extends Data
{
    public function __construct(
        public string $field,
        public string $code,
        public string $message,
    ) {}
}
