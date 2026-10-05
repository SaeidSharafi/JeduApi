<?php

declare(strict_types=1);

namespace App\Actions\Admin\ImportExport;

use App\Enums\ImportExport\ImportRunStatusEnum;
use App\Enums\ImportExport\UserProvisioningProviderEnum;
use App\Exceptions\Integrations\RecoverableProvisioningException;
use App\Exceptions\Integrations\UnrecoverableProvisioningException;
use App\Models\ImportRun;
use App\Models\ImportRunRow;
use App\Models\User;
use App\Models\UserProviderAccount;
use App\Services\ImportExport\UserProviderRegistry;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class ProvisionImportUserAction
{
    public const int MAX_ATTEMPTS = 3;

    public function __construct(private UserProviderRegistry $providers) {}

    public function handle(int $rowId, UserProvisioningProviderEnum $provider): void
    {
        $row            = ImportRunRow::query()->findOrFail($rowId);
        $idempotencyKey = 'import-user:'.$row->local_resource_id.':'.$provider->value;
        Cache::lock($idempotencyKey, 75)->block(2, function () use ($row, $provider, $idempotencyKey): void {
            $row->refresh();
            if (! $row->is_valid || $row->importRun->approved_at === null || ! in_array($row->providers[$provider->value]['status'] ?? null, ['queued', 'processing'], true)) {
                return;
            }
            $user    = User::query()->findOrFail($row->local_resource_id);
            $account = UserProviderAccount::query()->firstOrCreate(
                ['idempotency_key' => $idempotencyKey],
                ['user_id' => $user->id, 'provider' => $provider, 'status' => 'pending'],
            );
            $adapter = $this->providers->resolve($provider);
            if (in_array($account->status, ['processing', 'ambiguous'], true) && ! $adapter->canReplay()) {
                $this->record($row, $provider, 'failed', __('imports.provider_results.manual_verification'));

                return;
            }
            if ($account->status !== 'succeeded' && ($row->providers[$provider->value]['attempts'] ?? 0) >= self::MAX_ATTEMPTS) {
                $this->record($row, $provider, $adapter->canReplay() ? 'retryable_failed' : 'failed', __('imports.provider_results.failed'));

                return;
            }
            $this->record($row, $provider, 'processing', null, true);
            if ($account->status !== 'succeeded') {
                $account->update(['status' => 'processing']);
                try {
                    $adapter->ensureUser($user);
                    $account->update(['status' => 'succeeded']);
                } catch (Throwable $exception) {
                    $transient = $exception instanceof RecoverableProvisioningException || $exception instanceof ConnectionException;
                    $retryable = $transient              && $adapter->canReplay();
                    $ambiguous = ! $adapter->canReplay() && (! ($exception instanceof UnrecoverableProvisioningException) || ($exception->metaData['ambiguous_outcome'] ?? false));
                    $account->update(['status' => $ambiguous ? 'ambiguous' : 'pending']);
                    if ($retryable && $row->providers[$provider->value]['attempts'] < self::MAX_ATTEMPTS) {
                        $this->record($row, $provider, 'queued', __('imports.provider_results.retrying'));

                        throw $exception;
                    }
                    $this->record($row, $provider, $retryable ? 'retryable_failed' : 'failed',
                        __($ambiguous ? 'imports.provider_results.manual_verification' : 'imports.provider_results.failed'));

                    return;
                }
            }
            $this->record($row, $provider, 'succeeded', null);
        });
    }

    public function fail(int $rowId, UserProvisioningProviderEnum $provider, ?Throwable $exception = null): void
    {
        $row = ImportRunRow::query()->find($rowId);
        if ($row === null) {
            return;
        }
        DB::transaction(function () use ($row, $provider, $exception): void {
            ImportRun::query()->lockForUpdate()->findOrFail($row->import_run_id);
            $row->refresh();
            if (! in_array($row->providers[$provider->value]['status'] ?? null, ['queued', 'processing'], true)) {
                return;
            }
            $retryable = $this->providers->resolve($provider)->canReplay()
                && ($exception instanceof RecoverableProvisioningException
                    || $exception instanceof ConnectionException
                    || $exception instanceof MaxAttemptsExceededException
                    || $exception instanceof LockTimeoutException);
            $this->record($row, $provider, $retryable ? 'retryable_failed' : 'failed', __('imports.provider_results.failed'));
        });
    }

    private function record(ImportRunRow $row, UserProvisioningProviderEnum $provider, string $status, ?string $message, bool $incrementAttempts = false): void
    {
        DB::transaction(function () use ($row, $provider, $status, $message, $incrementAttempts): void {
            $run = ImportRun::query()->lockForUpdate()->findOrFail($row->import_run_id);
            $row->refresh();
            $outcomes                   = $row->providers;
            $outcomes[$provider->value] = ['status' => $status, 'message' => $message, 'attempts' => ($outcomes[$provider->value]['attempts'] ?? 0) + (int) $incrementAttempts];
            $row->update(['providers' => $outcomes]);
            $pending = $failed = false;
            foreach ($run->rows()->pluck('providers') as $providerOutcomes) {
                foreach ($providerOutcomes ?? [] as $outcome) {
                    $pending = $pending || in_array($outcome['status'], ['queued', 'processing'], true);
                    $failed  = $failed  || in_array($outcome['status'], ['failed', 'retryable_failed'], true);
                }
            }
            $status = $pending ? ImportRunStatusEnum::PROCESSING : ($failed ? ImportRunStatusEnum::COMPLETED_WITH_PROVIDER_FAILURES : ImportRunStatusEnum::COMPLETED);
            $run->update([
                'status'               => $status,
                'artifacts_expires_at' => $run->status === ImportRunStatusEnum::PROCESSING && $status !== ImportRunStatusEnum::PROCESSING
                    ? now()->addHours((int) config('import-export.retention_hours', 24))
                    : $run->artifacts_expires_at,
            ]);
        });
    }
}
