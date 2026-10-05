<?php

declare(strict_types=1);

namespace App\Actions\Admin\ImportExport;

use App\Models\ImportExportArtifact;
use App\Services\ImportExport\SpreadsheetResourceRegistry;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final readonly class DownloadExportAction
{
    public function __construct(private SpreadsheetResourceRegistry $registry) {}

    public function handle(string $resource, string $artifact): BinaryFileResponse
    {
        $contract = $this->registry->export($resource);
        $contract->authorize();
        $disk   = Storage::disk('local');
        $path   = "exports/{$resource}/{$artifact}.xlsx";
        $record = ImportExportArtifact::query()->where('resource', $resource)->where('artifact_uuid', $artifact)->first();
        abort_unless($record !== null && $record->expires_at->isFuture(), 404);
        abort_unless($record->path === $path, 404);
        abort_unless($disk->exists($path), 404);

        return response()->download($disk->path($path), "{$resource}-export.xlsx", [
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
