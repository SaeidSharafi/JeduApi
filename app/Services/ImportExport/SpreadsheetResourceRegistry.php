<?php

declare(strict_types=1);

namespace App\Services\ImportExport;

use App\Contracts\ImportExport\ImportResourceContract;
use App\Exceptions\ImportExport\UnknownImportResourceException;
use App\Services\ImportExport\Resources\UserImportResource;

/**
 * Fixed server-side registry of importable resources.
 *
 * Resource names coming from the request only ever select a handler that was
 * registered here; they are never resolved to class names. Export handlers are
 * registered here by the export ticket.
 */
final class SpreadsheetResourceRegistry
{
    /** @var array<string, ImportResourceContract> */
    private array $imports = [];

    public function __construct(
        UserImportResource $userImport,
    ) {
        $this->imports[$userImport->resource()->value] = $userImport;
    }

    public function import(string $resource): ImportResourceContract
    {
        return $this->imports[$resource] ?? throw UnknownImportResourceException::forResource($resource);
    }
}
