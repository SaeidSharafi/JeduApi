<?php

declare(strict_types=1);

namespace App\Actions\Testing;

use App\Exceptions\Testing\E2eResetFailedException;
use App\Models\Staff;
use App\Models\User;
use App\Services\Testing\E2eResetState;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use SaeidSharafi\LaravelPermissionGenerator\Commands\PermissionsSync;
use Throwable;

final class ResetE2eEnvironmentAction
{
    private const LOCK_KEY = 'e2e:database-reset';

    private const PASSWORD = 'password123';

    /**
     * @return array{reset_id: string, readiness: string, staff: array<string, mixed>, customer: array<string, mixed>}|null
     */
    public function handle(): ?array
    {
        $resetId = (string) Str::uuid7();
        try {
            $store = Cache::store('e2e')->getStore();

            if (! $store instanceof LockProvider) {
                throw new RuntimeException('The E2E cache store does not support distributed locks.');
            }

            $lock = $store->lock(self::LOCK_KEY, (int) config('e2e.reset_lock_seconds', 300));
        } catch (Throwable $exception) {
            Log::error('E2E reset lock acquisition failed.', [
                'reset_id'  => $resetId,
                'error'     => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            throw new E2eResetFailedException($resetId, 'E2E environment reset failed.', $exception);
        }

        if (! $lock->get()) {
            return null;
        }

        $state        = app(E2eResetState::class);
        $stateStarted = false;

        try {
            $state->begin($resetId, (int) config('e2e.reset_state_ttl_seconds', 900));
            $stateStarted = true;
            $this->pauseWorkers();
            $this->waitForActiveJobs($resetId);
            $state->clearWorkerHeartbeats();
            $this->terminateWorkers();
            Redis::connection('default')->flushdb();
            Cache::store('e2e')->flush();
            $this->clearMedia();

            $data = $this->reset($resetId);

            $this->finishResetState($state, $resetId);
            $stateStarted = false;
            $this->waitForWorkerReady($resetId);

            return $data;
        } catch (Throwable $exception) {
            if ($stateStarted) {
                try {
                    $state->finish();
                } catch (Throwable $stateException) {
                    Log::critical('E2E reset state could not be cleared after failure.', [
                        'reset_id'  => $resetId,
                        'error'     => $stateException->getMessage(),
                        'exception' => $stateException::class,
                    ]);
                }
            }

            try {
                $this->terminateWorkers();
            } catch (Throwable $terminateException) {
                Log::critical('E2E workers could not be terminated after reset failure.', [
                    'reset_id' => $resetId,
                    'error'    => $terminateException->getMessage(),
                ]);
            }

            Log::error('E2E environment reset failed.', [
                'reset_id'  => $resetId,
                'error'     => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            throw new E2eResetFailedException($resetId, 'E2E environment reset failed.', $exception);
        } finally {
            $lock->release();
        }
    }

    /**
     * @return array{reset_id: string, readiness: string, staff: array<string, mixed>, customer: array<string, mixed>}
     */
    private function reset(string $resetId): array
    {
        $this->runCommand('migrate:fresh', ['--force' => true], 'The E2E database could not be rebuilt.');

        // Package registers permissions:sync only when runningInConsole();
        // FrankenPHP requests are not, so register it manually.
        app(ConsoleKernel::class)->registerCommand(app(PermissionsSync::class));

        $this->runCommand('permissions:sync', ['--guard' => 'staff'], 'Staff permissions could not be synchronized.');
        $this->runCommand('permissions:sync', ['--guard' => 'user'], 'User permissions could not be synchronized.');

        /** @var Staff $staff */
        $staff = Staff::factory()->isSuperAdmin()->create([
            'name'     => 'E2E Admin',
            'email'    => $this->credential('admin'),
            'phone'    => '0912'.random_int(1000000, 9999999),
            'password' => Hash::make(self::PASSWORD),
        ]);
        /** @var User $customer */
        $customer = User::factory()->withPassword()->create([
            'email' => $this->credential('customer'),
            'phone' => '0911'.random_int(1000000, 9999999),
        ]);

        return [
            'reset_id'  => $resetId,
            'readiness' => 'ready',
            'staff'     => [
                'id'       => $staff->id,
                'email'    => $staff->email,
                'phone'    => $staff->phone,
                'password' => self::PASSWORD,
                'token'    => $staff->createToken('e2e-reset')->plainTextToken,
            ],
            'customer' => [
                'id'       => $customer->id,
                'email'    => $customer->email,
                'phone'    => $customer->phone,
                'password' => self::PASSWORD,
                'token'    => $customer->createToken('e2e-reset')->plainTextToken,
            ],
        ];
    }

    private function credential(string $prefix): string
    {
        return $prefix.'+'.Str::lower(Str::random(12)).'@jedu.test';
    }

    private function pauseWorkers(): void
    {
        if (Artisan::call('horizon:pause') !== 0) {
            throw new RuntimeException('Horizon workers could not be paused.');
        }
    }

    private function terminateWorkers(): void
    {
        if (Artisan::call('horizon:terminate') !== 0) {
            throw new RuntimeException('Horizon workers could not be terminated.');
        }
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function runCommand(string $command, array $parameters, string $failureMessage): void
    {
        if (Artisan::call($command, $parameters) !== 0) {
            throw new RuntimeException($failureMessage);
        }
    }

    private function finishResetState(E2eResetState $state, string $resetId): void
    {
        try {
            $state->finish();
        } catch (Throwable $exception) {
            Log::error('E2E reset state could not be cleared.', [
                'reset_id'  => $resetId,
                'error'     => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            throw $exception;
        }
    }

    private function waitForActiveJobs(string $resetId): void
    {
        $state    = app(E2eResetState::class);
        $deadline = microtime(true) + (int) config('e2e.worker_ready_timeout', 15);

        while ($state->activeJobCount() > 0) {
            if (microtime(true) >= $deadline) {
                throw new RuntimeException("Active E2E jobs did not drain for reset [{$resetId}].");
            }

            usleep(100_000);
        }
    }

    private function waitForWorkerReady(string $resetId): void
    {
        $state    = app(E2eResetState::class);
        $deadline = microtime(true) + (int) config('e2e.worker_ready_timeout', 15);

        while (! $state->hasReadyWorker()) {
            if (microtime(true) >= $deadline) {
                throw new RuntimeException("No E2E worker became ready for reset [{$resetId}].");
            }

            usleep(100_000);
        }
    }

    private function clearMedia(): void
    {
        $disk = Storage::disk((string) config('e2e.media_disk', 'e2e'));
        $disk->delete($disk->allFiles());

        foreach (array_reverse($disk->allDirectories()) as $directory) {
            $disk->deleteDirectory($directory);
        }
    }
}
