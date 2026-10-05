<?php

declare(strict_types=1);

namespace App\Services\ImportExport;

use App\Contracts\ImportExport\ExportResourceContract;
use App\Contracts\ImportExport\ImportResourceContract;
use App\Exceptions\ImportExport\UnknownImportResourceException;
use App\Services\ImportExport\Resources\UserExportResource;
use App\Services\ImportExport\Resources\UserImportResource;

/**
 * Fixed server-side registry of spreadsheet resources.
 *
 * Resource names coming from the request only ever select a handler that was
 * registered here; they are never resolved to class names.
 */
final class SpreadsheetResourceRegistry
{
    /** @var array<string, ImportResourceContract> */
    private array $imports = [];

    /** @var array<string, ExportResourceContract> */
    private array $exports = [];

    public function __construct(
        UserImportResource $userImport,
        UserExportResource $userExport,
    ) {
        $this->imports[$userImport->resource()->value] = $userImport;
        $this->exports[$userExport->resource()->value] = $userExport;
    }

    public function export(string $resource): ExportResourceContract
    {
        return $this->exports[$resource] ?? throw UnknownImportResourceException::forResource($resource);
    }

    public function import(string $resource): ImportResourceContract
    {
        return $this->imports[$resource] ?? throw UnknownImportResourceException::forResource($resource);
    }
}
