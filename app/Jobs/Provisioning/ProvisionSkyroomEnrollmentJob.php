<?php

declare(strict_types=1);

namespace App\Jobs\Provisioning;

use App\Exceptions\Integrations\UnrecoverableProvisioningException;
use App\Models\Enrollment;
use App\Services\Integrations\SkyroomService;

final class ProvisionSkyroomEnrollmentJob extends AbstractProvisioningJob
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
        return 'skyroom';
    }

    protected function executeProvisioning(): void
    {
        /** @var SkyroomService $service */
        $service = app(SkyroomService::class);

        // Silently skip — integration toggled off, not a failure.
        if (! $service->isEnabled()) {
            return;
        }

        // Missing api_key → UnrecoverableProvisioningException → job fails immediately.
        $service->assertConfigured();

        $enrollment = $this->getEnrollment();
        if (! $enrollment) {
            return;
        }

        $details = $enrollment->productDeliveryOption?->details_json ?? [];
        $roomId  = data_get($details, 'room_id');

        // A non-numeric room_id in the DB will never fix itself on retry.
        if (! is_numeric($roomId)) {
            throw new UnrecoverableProvisioningException(
                __('messages.provisioning.skyroom_room_id_missing')
            );
        }
        $roomId = (int) $roomId;

        $result        = $service->findOrCreateUser($enrollment->customer);
        $skyroomUserId = $result['skyroom_user_id'];

        $service->addUserToRoom($roomId, $skyroomUserId);

        $this->markProvisioningSuccess($enrollment, 'skyroom', [
            'room_id'         => $roomId,
            'skyroom_user_id' => $skyroomUserId,
        ]);
    }
}
