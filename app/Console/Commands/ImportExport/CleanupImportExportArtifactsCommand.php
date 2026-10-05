<?php

declare(strict_types=1);

namespace App\Console\Commands\ImportExport;

use App\Enums\ImportExport\ImportRunStatusEnum;
use App\Enums\ImportExport\SpreadsheetResourceEnum;
use App\Enums\ImportExport\UserProvisioningProviderEnum;
use App\Models\ImportExportArtifact;
use App\Models\ImportRun;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final class CleanupImportExportArtifactsCommand extends Command
{
    protected $signature = 'imports:cleanup-artifacts {--batch=100}';

    protected $description = 'Expire private import and export artifacts and redact expired snapshots';

    public function handle(): int
    {
        $batch = max(1, min(500, (int) $this->option('batch')));
        ImportExportArtifact::query()
            ->where('expires_at', '<=', now())
            ->where(fn (Builder $query) => $query->whereNull('cleanup_retry_after')->orWhere('cleanup_retry_after', '<=', now()))
            ->orderByRaw('cleanup_retry_after IS NOT NULL')
            ->orderBy('cleanup_retry_after')
            ->orderBy('id')
            ->limit($batch)
            ->get()
            ->each(fn (ImportExportArtifact $artifact): bool => $this->deleteExport($artifact));

        ImportRun::query()
            ->where('status', '!=', ImportRunStatusEnum::PROCESSING->value)
            ->whereNull('artifacts_redacted_at')
            ->where('artifacts_expires_at', '<=', now())
            ->where(fn (Builder $query) => $query->whereNull('artifacts_cleanup_retry_after')->orWhere('artifacts_cleanup_retry_after', '<=', now()))
            ->orderByRaw('artifacts_cleanup_retry_after IS NOT NULL')
            ->orderBy('artifacts_cleanup_retry_after')
            ->orderBy('id')
            ->limit($batch)
            ->pluck('id')
            ->each(fn (int $id): bool => $this->expireRun($id));

        return self::SUCCESS;
    }

    private function expireRun(int $id): bool
    {
        try {
            return DB::transaction(function () use ($id): bool {
                $run = ImportRun::query()->lockForUpdate()->find($id);
                if ($run === null || $run->status === ImportRunStatusEnum::PROCESSING || $run->artifacts_redacted_at !== null) {
                    return false;
                }

                $expiresAt = $run->artifacts_expires_at;
                if ($expiresAt->isFuture()) {
                    return false;
                }

                $disk = Storage::disk('local');
                foreach (array_unique(array_filter([$run->file_path, $run->error_report_path])) as $path) {
                    if (! preg_match('/^imports\/'.preg_quote($run->uuid, '/').'\/[^\/]+$/', $path)) {
                        throw new RuntimeException('Import artifact reference is outside its run directory.');
                    }
                    if ($disk->exists($path) && ! $disk->delete($path)) {
                        throw new RuntimeException('Private artifact deletion failed.');
                    }
                }

                foreach ($run->rows()->get() as $row) {
                    $safeProviders = [];
                    foreach ($row->providers ?? [] as $provider => $outcome) {
                        if (! in_array($provider, array_column(UserProvisioningProviderEnum::cases(), 'value'), true)) {
                            continue;
                        }
                        $status = $outcome['status'] ?? null;
                        if (! in_array($status, ['queued', 'processing', 'succeeded', 'failed', 'retryable_failed'], true)) {
                            continue;
                        }
                        $safeProviders[$provider] = [
                            'status'   => $status,
                            'message'  => null,
                            'attempts' => (int) ($outcome['attempts'] ?? 0),
                        ];
                    }
                    $row->update([
                        'identity_value'     => null,
                        'target_resource_id' => null,
                        'data'               => null,
                        'errors'             => null,
                        'local_resource_id'  => null,
                        'local_result_data'  => null,
                        'providers'          => $safeProviders,
                    ]);
                }

                $run->update([
                    'original_filename'        => '',
                    'file_path'                => null,
                    'file_checksum'            => null,
                    'error_report_path'        => null,
                    'error_report_fingerprint' => null,
                    'artifacts_redacted_at'    => now(),
                ]);

                return true;
            });
        } catch (Throwable $exception) {
            ImportRun::query()
                ->whereKey($id)
                ->where('status', '!=', ImportRunStatusEnum::PROCESSING->value)
                ->whereNull('artifacts_redacted_at')
                ->update(['artifacts_cleanup_retry_after' => now()->addHour()]);
            Log::warning('Could not clean expired import artifacts.', ['import_run_id' => $id]);

            return false;
        }
    }

    private function deleteExport(ImportExportArtifact $artifact): bool
    {
        try {
            if (SpreadsheetResourceEnum::tryFrom($artifact->resource) === null
                || $artifact->path !== "exports/{$artifact->resource}/{$artifact->artifact_uuid}.xlsx") {
                throw new RuntimeException('Export artifact reference is outside its trusted resource directory.');
            }
            $disk = Storage::disk('local');
            if ($disk->exists($artifact->path) && ! $disk->delete($artifact->path)) {
                $artifact->update(['cleanup_retry_after' => now()->addHour()]);

                return false;
            }
            $artifact->delete();

            return true;
        } catch (Throwable) {
            $artifact->update(['cleanup_retry_after' => now()->addHour()]);
            Log::warning('Could not clean expired export artifact.', ['artifact_id' => $artifact->id]);

            return false;
        }
    }
}
