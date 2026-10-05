<?php

declare(strict_types=1);

namespace App\Data\ImportExport;

/** Safe resource-owned result of a committed local row. */
final readonly class ImportRowCommitResult
{
    /** @param array<string, mixed> $data */
    public function __construct(public string $resourceId, public array $data) {}
}
