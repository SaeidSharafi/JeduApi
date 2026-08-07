<?php

declare(strict_types=1);

namespace App\Jobs\Provisioning;

use App\Exceptions\Integrations\UnrecoverableProvisioningException;
use App\Models\Enrollment;
use App\Services\Integrations\MoodleService;

final class ProvisionMoodleQuizJob extends AbstractProvisioningJob
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
        return 'moodle_quiz';
    }

    protected function executeProvisioning(): void
    {
        /** @var MoodleService $service */
        $service = app(MoodleService::class);

        // Silently skip — integration toggled off, not a failure.
        if (! $service->isEnabled()) {
            return;
        }

        // Missing base_url / token → UnrecoverableProvisioningException → job fails immediately.
        $service->assertConfigured();

        $enrollment = $this->getEnrollment();
        if (! $enrollment) {
            return;
        }

        $details  = $enrollment->productDeliveryOption->details_json ?? [];
        $courseId = data_get($details, 'moodle_quiz_course_id');

        // A non-numeric course_id in the DB will never fix itself on retry.
        if (! is_numeric($courseId)) {
            throw new UnrecoverableProvisioningException(
                __('messages.provisioning.moodle_quiz_course_id_missing')
            );
        }
        $courseId = (int) $courseId;

        [$moodleUserId, $moodleUsername] = $service->findOrCreateUser($enrollment->customer);

        // Quiz enrollments have no date window — enroll immediately with no expiry.
        $service->enrollUser($moodleUserId, $courseId, null, null, $service->getDefaultRoleId());

        $this->markProvisioningSuccess($enrollment, 'moodle_quiz', [
            'moodle_user_id'   => $moodleUserId,
            'moodle_username'  => $moodleUsername,
            'moodle_course_id' => $courseId,
        ]);
    }
}
