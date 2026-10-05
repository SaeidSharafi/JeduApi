<?php

declare(strict_types=1);

namespace App\Actions\Admin\ImportExport;

use App\Data\Admin\ImportExport\ImportRunData;
use App\Data\Admin\ImportExport\ImportRunRowData;
use App\Data\Admin\ImportExport\ImportRunSummaryData;
use App\Models\ImportRun;
use App\Services\ImportExport\SpreadsheetResourceRegistry;
use Illuminate\Support\Facades\Log;
use Throwable;

final class GetImportRunAction
{
    public function __construct(
        private BuildImportErrorReportAction $errorReports,
        private SpreadsheetResourceRegistry $registry,
    ) {}

    public function handle(string $resource, string $uuid): ImportRunData
    {
        $run       = ImportRun::query()->where('resource', $resource)->where('uuid', $uuid)->with('rows')->firstOrFail();
        $rows      = [];
        $successes = $failures = $retryable = 0;
        $contract  = $this->registry->import($resource);
        foreach ($run->rows->sortBy('row_number') as $row) {
            $providers = [];
            foreach ($row->providers ?? [] as $provider => $outcome) {
                $providers[$provider] = ['status' => $outcome['status'], 'message' => $outcome['message']];
                $successes += (int) ($outcome['status'] === 'succeeded');
                $failures  += (int) in_array($outcome['status'], ['failed', 'retryable_failed'], true);
                $retryable += (int) ($outcome['status'] === 'retryable_failed');
            }
            $rows[] = new ImportRunRowData(
                row_number: $row->row_number,
                status: ! $row->is_valid ? 'invalid' : ($run->approved_at === null ? 'valid' : 'completed'),
                operation: $run->approved_at === null ? $row->action?->value : match ($row->action?->value) {
                    'create' => 'created', 'update' => 'updated', default => null,
                },
                data: $row->local_result_data ?? $row->data,
                providers: $providers,
                errors: array_map(static function (array $error) use ($contract): array {
                    $error['message'] = $contract->errorReportMessage((string) ($error['code'] ?? 'invalid'));

                    return $error;
                }, $row->errors ?? []),
            );
        }

        $errorReport = ['available' => false, 'download_url' => null];
        try {
            if ($this->errorReports->handle($resource, $uuid) !== null) {
                $errorReport = [
                    'available'    => true,
                    'download_url' => route('api.v1.admin.imports.errors', ['resource' => $resource, 'run' => $uuid], false),
                ];
            }
        } catch (Throwable) {
            Log::warning('Could not prepare import error report.', ['resource' => $resource, 'run_id' => $uuid]);
        }

        return new ImportRunData(
            run_id: $run->uuid,
            resource: $run->resource,
            status: $run->status,
            identity_key: $run->identity_key,
            summary: new ImportRunSummaryData(
                total_rows: $run->rows_total,
                valid_rows: $run->rows_valid,
                invalid_rows: $run->rows_invalid,
                created_count: $run->created_count,
                updated_count: $run->updated_count,
                local_failure_count: 0,
                provider_success_count: $successes,
                provider_failure_count: $failures,
                retryable_provider_failure_count: $retryable,
            ),
            rows: $rows,
            error_report: $errorReport,
        );
    }
}
