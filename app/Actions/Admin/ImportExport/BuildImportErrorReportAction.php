<?php

declare(strict_types=1);

namespace App\Actions\Admin\ImportExport;

use App\Enums\ImportExport\ImportRunStatusEnum;
use App\Enums\ImportExport\UserProvisioningProviderEnum;
use App\Models\ImportRun;
use App\Models\ImportRunRow;
use App\Services\ImportExport\SpreadsheetResourceRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Throwable;

final readonly class BuildImportErrorReportAction
{
    private const string DISK = 'local';

    private const array HEADINGS = ['row_number', 'field', 'code', 'message', 'provider'];

    public function __construct(private SpreadsheetResourceRegistry $registry) {}

    /** Generate or reuse the current private artifact and return its metadata. */
    public function handle(string $resource, string $uuid): ?string
    {
        return DB::transaction(function () use ($resource, $uuid): ?string {
            $run = ImportRun::query()->where('resource', $resource)->where('uuid', $uuid)->lockForUpdate()->firstOrFail();
            if ($run->artifacts_expires_at->lessThanOrEqualTo(now())) {
                return null;
            }
            $rows    = $run->rows()->orderBy('row_number')->get();
            $records = $this->records($run, $rows->all());

            if ($records === []) {
                $oldPath = $run->error_report_path;
                $run->update(['error_report_path' => null, 'error_report_fingerprint' => null]);
                if ($oldPath !== null) {
                    DB::afterCommit(static fn () => Storage::disk(self::DISK)->delete($oldPath));
                }

                return null;
            }

            $fingerprint = hash('sha256', json_encode($records, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
            $disk        = Storage::disk(self::DISK);
            if ($run->error_report_fingerprint === $fingerprint
                && $run->error_report_path !== null
                && $disk->exists($run->error_report_path)) {
                return $run->error_report_path;
            }

            $path          = "imports/{$run->uuid}/errors-{$fingerprint}.xlsx";
            $temporaryPath = tempnam(sys_get_temp_dir(), 'import-errors-');
            if ($temporaryPath === false) {
                throw new RuntimeException('Could not allocate a temporary error report file.');
            }

            try {
                $this->writeWorkbook($records, $temporaryPath);
                $contents = file_get_contents($temporaryPath);
                if ($contents === false || ! $disk->put($path, $contents, 'private')) {
                    throw new RuntimeException('Could not store the import error report.');
                }
                $oldPath = $run->error_report_path;
                $run->update(['error_report_path' => $path, 'error_report_fingerprint' => $fingerprint]);
                if ($oldPath !== null && $oldPath !== $path) {
                    DB::afterCommit(static fn () => $disk->delete($oldPath));
                }
            } catch (Throwable $exception) {
                $disk->delete($path);
                throw $exception;
            } finally {
                @unlink($temporaryPath);
            }

            return $path;
        });
    }

    /** @param list<ImportRunRow> $rows
     * @return list<array{row_number: int, field: string, code: string, message: string, provider: string}>
     */
    private function records(ImportRun $run, array $rows): array
    {
        $contract  = $this->registry->import($run->resource->value);
        $providers = array_map(static fn (UserProvisioningProviderEnum $provider): string => $provider->value, $contract->providerCapabilities());
        $records   = [];
        foreach ($rows as $row) {
            if (! $row->is_valid) {
                foreach ($row->errors ?? [] as $error) {
                    $field     = $contract->errorReportFieldLabel((string) ($error['field'] ?? ''));
                    $code      = (string) ($error['code'] ?? 'invalid');
                    $records[] = [
                        'row_number' => $row->row_number,
                        'field'      => $field ?? '',
                        'code'       => $contract->errorReportCode($code),
                        'message'    => $contract->errorReportMessage($code),
                        'provider'   => '',
                    ];
                }
            }
            if ($run->status === ImportRunStatusEnum::PROCESSING) {
                continue;
            }
            foreach ($row->providers ?? [] as $provider => $outcome) {
                if (! in_array($outcome['status'] ?? null, ['failed', 'retryable_failed'], true)) {
                    continue;
                }
                $records[] = [
                    'row_number' => $row->row_number,
                    'field'      => '',
                    'code'       => 'provider_failed',
                    'message'    => (string) __('imports.error_report.provider_failed'),
                    'provider'   => in_array($provider, $providers, true) ? $provider : '',
                ];
            }
        }

        usort($records, static fn (array $left, array $right): int => [$left['row_number'], $left['field'], $left['provider']] <=> [$right['row_number'], $right['field'], $right['provider']]);

        return $records;
    }

    /** @param list<array{row_number: int, field: string, code: string, message: string, provider: string}> $records */
    private function writeWorkbook(array $records, string $path): void
    {
        $workbook = new Spreadsheet();
        $sheet    = $workbook->getActiveSheet();
        foreach (self::HEADINGS as $index => $heading) {
            $sheet->getCell([$index + 1, 1])->setValueExplicit($heading, DataType::TYPE_STRING);
        }
        foreach ($records as $rowIndex => $record) {
            foreach (array_values($record) as $columnIndex => $value) {
                $sheet->getCell([$columnIndex + 1, $rowIndex + 2])->setValueExplicit((string) $value, DataType::TYPE_STRING);
            }
        }
        (new Xlsx($workbook))->save($path);
        $workbook->disconnectWorksheets();
    }
}
