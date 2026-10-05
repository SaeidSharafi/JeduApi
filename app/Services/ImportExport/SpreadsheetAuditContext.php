<?php

declare(strict_types=1);

namespace App\Services\ImportExport;

/** Request-scoped safe operation details shared with the admin audit middleware. */
final class SpreadsheetAuditContext
{
    private ?int $exportedRows = null;

    public function reset(): void
    {
        $this->exportedRows = null;
    }

    public function recordExportedRows(int $rows): void
    {
        $this->exportedRows = max(0, $rows);
    }

    public function exportedRows(): ?int
    {
        return $this->exportedRows;
    }
}
