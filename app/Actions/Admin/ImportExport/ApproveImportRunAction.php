<?php

declare(strict_types=1);

namespace App\Actions\Admin\ImportExport;

use App\Contracts\ImportExport\ImportResourceContract;
use App\Data\Admin\ImportExport\ImportApprovalData;
use App\Data\Admin\ImportExport\ImportApprovalRequestData;
use App\Data\Admin\ImportExport\ImportApprovalSummaryData;
use App\Data\ImportExport\ImportRowResult;
use App\Enums\ImportExport\ImportRowActionEnum;
use App\Enums\ImportExport\ImportRunStatusEnum;
use App\Models\ImportRun;
use App\Models\ImportRunRow;
use App\Services\ImportExport\ImportPreviewEngine;
use App\Services\ImportExport\SpreadsheetResourceRegistry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\Optional;

final readonly class ApproveImportRunAction
{
    private const string DISK = 'local';

    public function __construct(
        private SpreadsheetResourceRegistry $registry,
        private ImportPreviewEngine $engine,
    ) {}

    public function handle(string $resource, string $runUuid, ImportApprovalRequestData $data): ImportApprovalData
    {
        return DB::transaction(function () use ($resource, $runUuid, $data): ImportApprovalData {
            $run = ImportRun::query()
                ->where('uuid', $runUuid)
                ->where('resource', $resource)
                ->lockForUpdate()
                ->firstOrFail();

            if ($run->approved_at !== null) {
                return $this->present($run);
            }

            if ($run->rows_valid === 0) {
                throw ValidationException::withMessages([
                    'run' => __('imports.errors.no_valid_rows'),
                ]);
            }

            if ($run->rows_invalid > 0 && ($data->include_valid_rows instanceof Optional || ! $data->include_valid_rows)) {
                throw ValidationException::withMessages([
                    'include_valid_rows' => __('imports.errors.include_valid_rows_required'),
                ]);
            }

            $contract     = $this->registry->import($resource);
            $freshResults = $this->revalidate($run, $contract);
            $storedRows   = $run->rows()->orderBy('row_number')->get()->keyBy('row_number');

            $this->assertSnapshotUnchanged($storedRows, $freshResults);

            $created = 0;
            $updated = 0;

            foreach ($storedRows as $rowNumber => $storedRow) {
                if (! $storedRow->is_valid) {
                    continue;
                }

                $result = $freshResults[$rowNumber];
                $contract->importRow($result);

                $result->action === ImportRowActionEnum::CREATE ? $created++ : $updated++;
            }

            $run->update([
                'status'        => ImportRunStatusEnum::PROCESSING,
                'created_count' => $created,
                'updated_count' => $updated,
                'approved_at'   => now(),
            ]);

            return $this->present($run->refresh());
        });
    }

    /**
     * @return array<int, ImportRowResult>
     */
    private function revalidate(ImportRun $run, ImportResourceContract $contract): array
    {
        $path = Storage::disk(self::DISK)->path($run->file_path);

        if (! is_file($path) || hash_file('sha256', $path) !== $run->file_checksum) {
            throw ValidationException::withMessages(['run' => __('imports.errors.snapshot_changed')]);
        }

        $file = new UploadedFile($path, $run->original_filename, null, null, true);

        return $this->engine->preview($contract, $run->identity_key, $file);
    }

    /**
     * @param  Collection<int, ImportRunRow>  $storedRows
     * @param  array<int, ImportRowResult>  $freshResults
     */
    private function assertSnapshotUnchanged(Collection $storedRows, array $freshResults): void
    {
        if ($storedRows->keys()->all() !== array_keys($freshResults)) {
            throw ValidationException::withMessages(['run' => __('imports.errors.snapshot_changed')]);
        }

        foreach ($storedRows as $rowNumber => $stored) {
            $fresh = $freshResults[$rowNumber];

            if ($stored->is_valid                              !== $fresh->valid
                || $stored->action                             !== $fresh->action
                || $stored->identity_value                     !== $fresh->identity
                || $stored->target_resource_id                 !== $fresh->targetResourceId
                || $this->canonicalJson($stored->data)         !== $this->canonicalJson($fresh->data)
                || $this->canonicalJson($stored->errors ?? []) !== $this->canonicalJson($fresh->errors)) {
                throw ValidationException::withMessages(['run' => __('imports.errors.snapshot_changed')]);
            }
        }
    }

    /**
     * JSONB does not preserve object-key order, so compare recursively sorted objects.
     *
     * @param  array<array-key, mixed>  $value
     */
    private function canonicalJson(array $value): string
    {
        return json_encode($this->canonicalize($value), JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<array-key, mixed>
     */
    private function canonicalize(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->canonicalize($item);
            }
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }

    private function present(ImportRun $run): ImportApprovalData
    {
        return new ImportApprovalData(
            run_id: $run->uuid,
            resource: $run->resource,
            status: $run->status,
            identity_key: $run->identity_key,
            summary: new ImportApprovalSummaryData(
                created_count: $run->created_count,
                updated_count: $run->updated_count,
                provider_queued_count: $run->provider_queued_count,
            ),
        );
    }
}
