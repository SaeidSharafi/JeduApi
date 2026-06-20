<?php

declare(strict_types=1);

namespace App\Jobs\Provisioning;

use App\Exceptions\Integrations\UnrecoverableProvisioningException;
use App\Jobs\Provisioning\Concerns\HandlesProvisioningStatus;
use App\Models\Enrollment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

abstract class AbstractProvisioningJob implements ShouldQueue
{
    use Dispatchable;
    use HandlesProvisioningStatus;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** Cached within a single handle() call only — not serialized. */
    private ?Enrollment $cachedEnrollment = null;

    abstract protected function resolveEnrollment(): ?Enrollment;

    abstract protected function getIntegrationName(): string;

    abstract protected function executeProvisioning(): void;

    /**
     * @return array<int, int>
     */
    final public function backoff(): array
    {
        return [60, 180, 600];
    }

    final public function handle(): void
    {
        try {
            $this->executeProvisioning();
        } catch (UnrecoverableProvisioningException $e) {
            // Config missing, 4xx, bad delivery-option data — fail the job immediately,
            // bypassing all remaining retry attempts.
            $this->fail($e);

            throw $e;
        }
        // RecoverableProvisioningException, ConnectionException, RuntimeException
        // all bubble naturally and let Laravel's retry mechanism take over.
    }

    final public function failed(Throwable $exception): void
    {
        $enrollment = $this->resolveEnrollment();
        if (! $enrollment) {
            return;
        }
        Log::info('Handling provisioning failure for enrollment', ['enrollment_id' => $enrollment->id, 'exception_class' => get_class($exception), 'getIntegrationName' => $this->getIntegrationName()]);
        $metaData        = property_exists($exception, 'metaData') ? $exception->metaData : [];
        $integrationName = $this->getIntegrationName();

        $this->markProvisioningFailure($enrollment, $integrationName, $exception->getMessage(), $metaData);

        Log::error(mb_strtoupper($integrationName).' provisioning failed', [
            'enrollment_id'   => $enrollment->id,
            'exception_class' => get_class($exception),
            'job_attempts'    => $this->attempts(),
            'meta'            => $metaData,
        ]);

        // Hook: subclasses that need extra failure actions (e.g. AdminActionLog) override this.
        $this->onFailed($enrollment, $exception, $metaData);
    }

    /**
     * Cached enrollment accessor. Saves repeated DB queries within executeProvisioning().
     * Note: failed() runs in a new process, so the cache is always cold there.
     */
    final protected function getEnrollment(): ?Enrollment
    {
        return $this->cachedEnrollment ??= $this->resolveEnrollment();
    }

    /**
     * Override in subclasses that need additional failure side-effects.
     */
    protected function onFailed(Enrollment $enrollment, Throwable $exception, array $metaData): void {}
}
