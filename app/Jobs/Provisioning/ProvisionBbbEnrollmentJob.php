<?php

declare(strict_types=1);

namespace App\Jobs\Provisioning;

use App\Exceptions\Integrations\UnrecoverableProvisioningException;
use App\Models\Enrollment;
use App\Services\Integrations\BbbService;

final class ProvisionBbbEnrollmentJob extends AbstractProvisioningJob
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
        return 'bbb';
    }

    protected function executeProvisioning(): void
    {
        /** @var BbbService $service */
        $service = app(BbbService::class);

        if (! $service->isEnabled()) {
            return;
        }

        $service->assertConfigured();

        $enrollment = $this->getEnrollment();
        if (! $enrollment) {
            return;
        }

        $details   = $enrollment->productDeliveryOption->details_json ?? [];
        $meetingId = data_get($details, 'meeting_id');

        // A missing meeting_id in the DB will never fix itself on retry.
        if (! is_string($meetingId) || $meetingId === '') {
            throw new UnrecoverableProvisioningException(
                __('messages.provisioning.bbb_meeting_id_missing')
            );
        }

        $autoCreate        = (bool) data_get($details, 'auto_create_meeting', false);
        $attendeePassword  = data_get($details, 'attendee_password');
        $moderatorPassword = data_get($details, 'moderator_password');

        if ($autoCreate) {
            $service->createMeeting(
                meetingId: $meetingId,
                name: $enrollment->productDeliveryOption->name ?? "meeting-{$meetingId}",
                attendeePw: is_string($attendeePassword) ? $attendeePassword : null,
                moderatorPw: is_string($moderatorPassword) ? $moderatorPassword : null,
            );
        }

        $this->markProvisioningSuccess($enrollment, 'bbb', [
            'meeting_id' => $meetingId,
        ]);
    }
}
