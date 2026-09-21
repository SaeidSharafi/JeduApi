<?php

declare(strict_types=1);

namespace App\Jobs\Provisioning;

use App\Actions\Shop\Student\ForgetStudentQuizCacheAction;
use App\Contracts\Provisioning\RevocationProvider;
use App\Exceptions\Integrations\UnrecoverableProvisioningException;
use App\Services\Provisioning\EnrollmentRevocationService;
use App\Services\Provisioning\ProvisioningAttemptService;
use App\Services\Provisioning\ProvisioningProviderRegistry;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Revokes one provider's external access for a single Enrollment.
 *
 * Mirrors ProvisionEnrollmentProviderJob's retry semantics but writes its
 * outcome through EnrollmentRevocationService, which owns the revocation state
 * machine instead of the provisioning-health projection.
 */
final class RevokeEnrollmentProviderJob implements ShouldBeUnique, ShouldQueue
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
        return 'revocation:'.$this->attemptId;
    }

    public function handle(
        ProvisioningAttemptService $attempts,
        ProvisioningProviderRegistry $providers,
        EnrollmentRevocationService $revocations,
    ): void {
        $attempt = $attempts->start($this->attemptId);
        if (! $attempt) {
            return;
        }

        $adapter = $providers->resolve($attempt->provider);
        if (! $adapter instanceof RevocationProvider) {
            $exception = new UnrecoverableProvisioningException(
                __('messages.provisioning.revocation_not_supported')
            );
            $revocations->fail($attempt, $exception, true);

            return;
        }

        try {
            $revocations->succeed($attempt, $adapter->revoke($attempt->enrollment));

            // Revocation removes the user's Moodle access, so the cached quiz list
            // must drop rather than wait out its fresh window.
            app(ForgetStudentQuizCacheAction::class)->handle($attempt->enrollment->customer_id);
        } catch (UnrecoverableProvisioningException $exception) {
            $revocations->fail($attempt, $exception, true, $exception->metaData);
            $this->fail($exception);

            throw $exception;
        } catch (Throwable $exception) {
            if ($this->attempts() < $this->tries) {
                $revocations->scheduleRetry($attempt);
            } else {
                $revocations->fail($attempt, $exception, false,
                    property_exists($exception, 'metaData') ? $exception->metaData : []);
            }

            throw $exception;
        }
    }
}
