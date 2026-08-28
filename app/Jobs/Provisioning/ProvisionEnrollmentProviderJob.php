<?php

declare(strict_types=1);

namespace App\Jobs\Provisioning;

use App\Exceptions\Integrations\UnrecoverableProvisioningException;
use App\Services\Provisioning\ProvisioningAttemptService;
use App\Services\Provisioning\ProvisioningProviderRegistry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

final class ProvisionEnrollmentProviderJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $attemptId) {}

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 180, 600];
    }

    public function handle(ProvisioningAttemptService $attempts, ProvisioningProviderRegistry $providers): void
    {
        $attempt = $attempts->start($this->attemptId);
        if (! $attempt) {
            return;
        }

        try {
            $references = $providers->resolve($attempt->provider)->provision($attempt->enrollment);
            $attempts->succeed($attempt, $references);
        } catch (UnrecoverableProvisioningException $exception) {
            $attempts->fail($attempt, $exception, true, $exception->metaData);
            $this->fail($exception);
            throw $exception;
        } catch (Throwable $exception) {
            if ($this->attempts() < $this->tries) {
                $attempts->scheduleRetry($attempt);
            } else {
                $attempts->fail($attempt, $exception, false, property_exists($exception, 'metaData') ? $exception->metaData : []);
            }
            throw $exception;
        }
    }
}
