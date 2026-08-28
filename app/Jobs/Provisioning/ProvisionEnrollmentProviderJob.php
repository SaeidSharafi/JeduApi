<?php

declare(strict_types=1);

namespace App\Jobs\Provisioning;

use App\Contracts\Provisioning\ProvisioningProvider;
use App\Exceptions\Integrations\UnrecoverableProvisioningException;
use App\Models\ProvisioningAttempt;
use App\Services\Provisioning\ProvisioningAttemptService;
use App\Services\Provisioning\ProvisioningProviderRegistry;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

final class ProvisionEnrollmentProviderJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 3600;

    public function __construct(public readonly int $attemptId) {}

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 180, 600];
    }

    public function uniqueId(): string
    {
        return (string) $this->attemptId;
    }

    public function handle(ProvisioningAttemptService $attempts, ProvisioningProviderRegistry $providers): void
    {
        $attempt = $attempts->start($this->attemptId);
        if (! $attempt) {
            return;
        }

        try {
            $provider   = $providers->resolve($attempt->provider);
            $references = $this->reconcileOrProvision($attempt, $provider);
            $attempts->succeed($attempt, $references);
        } catch (UnrecoverableProvisioningException $exception) {
            $attempts->fail($attempt, $exception, true, $exception->metaData);
            $this->fail($exception);
            throw $exception;
        } catch (Throwable $exception) {
            if ($this->attempts() < $this->tries) {
                $attempts->scheduleRetry($attempt);
            } else {
                $attempts->fail($attempt, $exception, false,
                    property_exists($exception, 'metaData') ? $exception->metaData : []);
            }
            throw $exception;
        }
    }

    /** @return array<string, mixed> */
    private function reconcileOrProvision(ProvisioningAttempt $attempt, ProvisioningProvider $provider): array
    {
        if ($this->shouldReconcile($attempt, $provider)) {
            return $provider->reconcileAccess($attempt->enrollment, $attempt->failure_metadata);
        }

        return $provider->provision($attempt->enrollment);
    }

    private function shouldReconcile(ProvisioningAttempt $attempt, ProvisioningProvider $provider): bool
    {
        return data_get($attempt->failure_metadata, 'kind') === 'access_reconciliation'
            && $provider->supportsAccessReconciliation();
    }
}
