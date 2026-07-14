<?php

declare(strict_types=1);

namespace App\Jobs\Provisioning;

use App\Exceptions\Integrations\UnrecoverableProvisioningException;
use App\Models\Enrollment;
use App\Services\Integrations\MoodleService;

final class ProvisionMoodleEnrollmentJob extends AbstractProvisioningJob
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
        return 'moodle';
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

        $details  = $enrollment->productDeliveryOption?->details_json ?? [];
        $courseId = data_get($details, 'moodle_course_id');

        // A non-numeric course_id in the DB will never fix itself on retry.
        if (! is_numeric($courseId)) {
            throw new UnrecoverableProvisioningException(
                __('messages.provisioning.moodle_course_id_missing')
            );
        }
        $courseId = (int) $courseId;

        [$moodleUserId, $moodleUsername] = $service->findOrCreateUser($enrollment->customer);

        $startDate = data_get($details, 'enrollment_start_date');
        $endDate   = data_get($details, 'enrollment_end_date');
        $startTime = is_string($startDate) && strtotime($startDate) !== false ? strtotime($startDate) : null;
        $endTime   = is_string($endDate)   && strtotime($endDate)   !== false ? strtotime($endDate) : null;

        // getCourse is called before enrollUser intentionally — it validates that the course
        // actually exists in Moodle and returns data needed for the success payload.
        $courseInfo = $service->getCourse($courseId);

        $service->enrollUser($moodleUserId, $courseId, $startTime, $endTime, $service->getDefaultRoleId());

        $this->markProvisioningSuccess($enrollment, 'moodle', [
            'moodle_user_id'   => $moodleUserId,
            'moodle_user_name' => $moodleUsername,
            'moodle_course_id' => $courseId,
            'login_path'       => $service->getLoginPath(),
            'course_info'      => $courseInfo,
        ]);
    }
}
