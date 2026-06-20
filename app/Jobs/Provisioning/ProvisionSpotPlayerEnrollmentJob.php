<?php

declare(strict_types=1);

namespace App\Jobs\Provisioning;

use App\Exceptions\Integrations\UnrecoverableProvisioningException;
use App\Models\Enrollment;
use App\Services\Integrations\SpotPlayerService;

final class ProvisionSpotPlayerEnrollmentJob extends AbstractProvisioningJob
{
    public function __construct(public readonly int $enrollmentId) {}

    protected function resolveEnrollment(): ?Enrollment
    {
        return Enrollment::query()
            ->with(['customer', 'productDeliveryOption'])
            ->find($this->enrollmentId);
    }

    protected function getIntegrationName(): string
    {
        return 'spotplayer';
    }

    protected function executeProvisioning(): void
    {
        /** @var SpotPlayerService $service */
        $service = app(SpotPlayerService::class);

        // Silently skip — integration toggled off, not a failure.
        if (! $service->isEnabled()) {
            return;
        }

        // Missing endpoint / api_key → UnrecoverableProvisioningException → job fails immediately.
        $service->assertConfigured();

        $enrollment = $this->getEnrollment();
        if (! $enrollment) {
            return;
        }

        $details = $enrollment->productDeliveryOption?->details_json ?? [];
        $spotId  = data_get($details, 'spot_id');

        // A missing spot_id in the DB will never fix itself on retry.
        if (! is_string($spotId) || $spotId === '') {
            throw new UnrecoverableProvisioningException(
                'SpotPlayer spot_id is missing from delivery option details.'
            );
        }

        // issueLicense throws UnrecoverableProvisioningException for application-level
        // errors (bad spot_id, status: false) and RecoverableProvisioningException for
        // HTTP 5xx. Both are handled correctly by AbstractProvisioningJob::handle().
        $result = $service->issueLicense($spotId, $enrollment->customer);

        $this->markProvisioningSuccess($enrollment, 'spotplayer', [
            'spot_id'     => $spotId,
            'license_key' => data_get($result, 'license_key'),
            'player_url'  => data_get($result, 'player_url'),
        ]);
    }
}
