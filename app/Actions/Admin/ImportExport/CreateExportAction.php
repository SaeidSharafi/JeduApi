<?php

declare(strict_types=1);

namespace App\Actions\Admin\ImportExport;

use App\Data\Admin\ImportExport\ExportArtifactData;
use App\Data\Admin\ImportExport\ExportRequestData;
use App\Services\ImportExport\ResourceSpreadsheetExport;
use App\Services\ImportExport\SpreadsheetResourceRegistry;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Throwable;

final readonly class CreateExportAction
{
    public function __construct(private SpreadsheetResourceRegistry $registry) {}

    public function handle(string $resource, ExportRequestData $data): ExportArtifactData
    {
        $contract = $this->registry->export($resource);
        $contract->authorize();
        $artifact = (string) Str::uuid();
        $path     = "exports/{$resource}/{$artifact}.xlsx";
        $expires  = now()->addDay();

        try {
            $stored = Excel::store(
                new ResourceSpreadsheetExport($contract, $data->locale ?? app()->getLocale()),
                $path,
                'local',
                ExcelWriter::XLSX,
                ['visibility' => 'private'],
            );

            if ($stored !== true) {
                throw new RuntimeException('Unable to store the export artifact.');
            }
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }

        return new ExportArtifactData(
            resource: $resource,
            filename: "{$resource}-export.xlsx",
            download_url: URL::temporarySignedRoute('api.v1.admin.exports.download', $expires, [
                'resource' => $resource,
                'artifact' => $artifact,
            ], absolute: false),
            expires_at: $expires->toISOString(),
        );
    }
}
