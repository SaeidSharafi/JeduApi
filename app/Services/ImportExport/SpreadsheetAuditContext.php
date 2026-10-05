<?php

declare(strict_types=1);

namespace App\Services\ImportExport;

/** Request-scoped safe operation details shared with the admin audit middleware. */
final class SpreadsheetAuditContext
{
    private ?int $exportedRows = null;

    private ?int $adminId = null;

    private ?float $startedAt = null;

    private ?string $operation = null;

    private ?bool $approvedBeforeRequest = null;

    private bool $auditAttempted = false;

    public function reset(): void
    {
        $this->exportedRows          = null;
        $this->adminId               = null;
        $this->startedAt             = null;
        $this->operation             = null;
        $this->approvedBeforeRequest = null;
        $this->auditAttempted        = false;
    }

    public function begin(string $operation, int $adminId, float $startedAt, ?bool $approvedBeforeRequest = null): void
    {
        $this->operation             = $operation;
        $this->adminId               = $adminId;
        $this->startedAt             = $startedAt;
        $this->approvedBeforeRequest = $approvedBeforeRequest;
    }

    public function adminId(): ?int
    {
        return $this->adminId;
    }

    public function startedAt(): ?float
    {
        return $this->startedAt;
    }

    public function operation(): ?string
    {
        return $this->operation;
    }

    public function approvedBeforeRequest(): ?bool
    {
        return $this->approvedBeforeRequest;
    }

    public function recordApprovalState(bool $wasAlreadyApproved): void
    {
        $this->approvedBeforeRequest = $wasAlreadyApproved;
    }

    public function recordExportedRows(int $rows): void
    {
        $this->exportedRows = max(0, $rows);
    }

    public function exportedRows(): ?int
    {
        return $this->exportedRows;
    }

    public function auditAttempted(): bool
    {
        return $this->auditAttempted;
    }

    public function markAuditAttempted(): void
    {
        $this->auditAttempted = true;
    }
}
