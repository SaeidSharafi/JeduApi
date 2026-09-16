<?php

declare(strict_types=1);

namespace App\Actions\Admin\ImportExport;

use App\Data\Admin\ImportExport\ImportPreviewData;
use App\Data\Admin\ImportExport\ImportPreviewRequestData;
use App\Data\Admin\ImportExport\ImportPreviewRowData;
use App\Data\Admin\ImportExport\ImportPreviewSummaryData;
use App\Data\Admin\ImportExport\ImportRowErrorData;
use App\Data\ImportExport\ImportRowResult;
use App\Enums\ImportExport\ImportIdentityKeyEnum;
use App\Enums\ImportExport\ImportRowActionEnum;
use App\Enums\ImportExport\ImportRowStatusEnum;
use App\Enums\ImportExport\ImportRunStatusEnum;
use App\Models\ImportRun;
use App\Models\ImportRunRow;
use App\Models\Staff;
use App\Services\ImportExport\ImportPreviewEngine;
use App\Services\ImportExport\SpreadsheetResourceRegistry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Validates an uploaded spreadsheet and stores it as an immutable Import Run.
 *
 * Preview never mutates stored resources and never calls a provider.
 */
final readonly class CreateImportPreviewAction
{
    private const string DISK = 'local';

    public function __construct(
        private SpreadsheetResourceRegistry $registry,
        private ImportPreviewEngine $engine,
    ) {}

    public function handle(string $resource, ImportPreviewRequestData $data, ?Staff $staff = null): ImportPreviewData
    {
        $contract    = $this->registry->import($resource);
        $identityKey = ImportIdentityKeyEnum::from($data->identity_key);

        $results = $this->engine->preview($contract, $identityKey, $data->file);

        $uuid     = (string) Str::uuid7();
        $filePath = $this->storeUpload($data->file, $uuid);

        try {
            $run = DB::transaction(fn (): ImportRun => $this->persistRun(
                $contract->resource()->value,
                $identityKey,
                $data->file,
                $uuid,
                $filePath,
                $staff,
                $results,
            ));
        } catch (Throwable $exception) {
            Storage::disk(self::DISK)->delete($filePath);

            throw $exception;
        }

        return $this->present($run);
    }

    /**
     * @param  array<int, ImportRowResult>  $results
     */
    private function persistRun(
        string $resource,
        ImportIdentityKeyEnum $identityKey,
        UploadedFile $file,
        string $uuid,
        string $filePath,
        ?Staff $staff,
        array $results,
    ): ImportRun {
        $total   = count($results);
        $valid   = count(array_filter($results, fn (ImportRowResult $result): bool => $result->valid));
        $invalid = $total - $valid;

        $run = ImportRun::create([
            'uuid'              => $uuid,
            'resource'          => $resource,
            'identity_key'      => $identityKey,
            'status'            => ImportRunStatusEnum::PREVIEW_READY,
            'staff_id'          => $staff?->getKey(),
            'original_filename' => $this->originalFilename($file),
            'file_path'         => $filePath,
            'file_size'         => (int) $file->getSize(),
            'rows_total'        => $total,
            'rows_valid'        => $valid,
            'rows_invalid'      => $invalid,
        ]);

        $run->rows()->createMany(array_map(
            static fn (int $rowNumber, ImportRowResult $result): array => [
                'row_number'     => $rowNumber,
                'identity_value' => $result->identity,
                'action'         => $result->action?->value,
                'is_valid'       => $result->valid,
                'errors'         => $result->errors,
                'data'           => $result->data,
            ],
            array_keys($results),
            $results,
        ));

        return $run->load('rows');
    }

    private function present(ImportRun $run): ImportPreviewData
    {
        $rows = $run->rows
            ->sortBy('row_number')
            ->map(static fn (ImportRunRow $row): ImportPreviewRowData => new ImportPreviewRowData(
                row_number: $row->row_number,
                status: $row->is_valid ? ImportRowStatusEnum::VALID : ImportRowStatusEnum::INVALID,
                operation: $row->action,
                data: $row->data,
                errors: array_map(
                    static fn (array $error): ImportRowErrorData => ImportRowErrorData::from($error),
                    $row->errors ?? [],
                ),
            ))
            ->values()
            ->all();

        return new ImportPreviewData(
            run_id: $run->uuid,
            resource: $run->resource,
            status: $run->status,
            identity_key: $run->identity_key,
            summary: new ImportPreviewSummaryData(
                total_rows: $run->rows_total,
                valid_rows: $run->rows_valid,
                invalid_rows: $run->rows_invalid,
                create_count: $run->rows->filter(fn (ImportRunRow $row): bool => $row->action === ImportRowActionEnum::CREATE)->count(),
                update_count: $run->rows->filter(fn (ImportRunRow $row): bool => $row->action === ImportRowActionEnum::UPDATE)->count(),
                provider_provisioning_request_count: $this->providerRequestCount($run),
            ),
            rows: $rows,
            can_approve: $run->status === ImportRunStatusEnum::PREVIEW_READY && $run->rows_valid > 0,
            approval_warning: (string) __('imports.approval_warning'),
        );
    }

    /**
     * Additive provider flags the preview asks for: one request per true flag.
     */
    private function providerRequestCount(ImportRun $run): int
    {
        $requests = 0;

        foreach ($run->rows as $row) {
            foreach ($row->data ?? [] as $key => $value) {
                if ($value === true && str_starts_with((string) $key, 'provision_')) {
                    $requests++;
                }
            }
        }

        return $requests;
    }

    private function storeUpload(UploadedFile $file, string $uuid): string
    {
        return (string) $file->storeAs(
            'imports/'.$uuid,
            $this->originalFilename($file),
            self::DISK,
        );
    }

    private function originalFilename(UploadedFile $file): string
    {
        $name = basename($file->getClientOriginalName());
        $name = (string) preg_replace('/[^\p{L}\p{N}._-]+/u', '_', $name);

        return $name === '' ? 'import.xlsx' : $name;
    }
}
