<?php

declare(strict_types=1);

namespace App\Services\Provisioning\Providers;

use App\Contracts\Integrations\MoodleClientContract;
use App\Contracts\Provisioning\ProvisioningProvider;
use App\Enums\EnrollmentStatusEnum;
use App\Enums\ProvisioningProviderEnum;
use App\Exceptions\Integrations\UnrecoverableProvisioningException;
use App\Models\Enrollment;
use Illuminate\Support\Carbon;

final readonly class MoodleProvisioningProvider implements ProvisioningProvider
{
    public function __construct(private MoodleClientContract $moodle) {}

    public function provider(): ProvisioningProviderEnum
    {
        return ProvisioningProviderEnum::MOODLE;
    }

    public function supportsAccessReconciliation(): bool
    {
        return true;
    }

    public function provision(Enrollment $enrollment): array
    {
        if (! $this->moodle->isEnabled()) {
            throw new UnrecoverableProvisioningException('Moodle provider is disabled.');
        }

        $this->moodle->assertConfigured();
        $details  = $enrollment->productDeliveryOption?->details_json ?? [];
        $courseId = data_get($details, 'moodle_course_id');
        if (! is_numeric($courseId)) {
            throw new UnrecoverableProvisioningException(
                __('messages.provisioning.moodle_course_id_missing')
            );
        }

        [$userId, $username] = $this->moodle->findOrCreateUser($enrollment->customer);
        $startDate           = data_get($details, 'enrollment_start_date');
        $endDate             = data_get($details, 'enrollment_end_date');
        $startTime           = is_string($startDate) && strtotime($startDate) !== false ? strtotime($startDate) : null;
        $endTime             = is_string($endDate)   && strtotime($endDate)   !== false ? strtotime($endDate) : null;

        $courseInfo = $this->moodle->getCourse((int) $courseId);
        $this->moodle->enrollUser($userId, (int) $courseId, $startTime, $endTime, $this->moodle->getDefaultRoleId());

        return [
            'moodle_user_id'   => $userId,
            'moodle_user_name' => $username,
            'moodle_course_id' => (int) $courseId,
            'course_url'       => $courseInfo->course_url,
            'login_path'       => $this->moodle->getLoginPath(),
            'provisioned_at'   => Carbon::now()->toISOString(),
        ];
    }

    /** @param  array<string, mixed>  $context */
    public function reconcileAccess(Enrollment $enrollment, array $context): array
    {
        $references = data_get($enrollment->provisioning_data, 'providers.moodle.data', []);
        $userId     = data_get($references, 'moodle_user_id');
        $courseId   = data_get($references, 'moodle_course_id');
        if (! is_numeric($userId) || ! is_numeric($courseId)) {
            throw new UnrecoverableProvisioningException('Moodle enrollment references are missing.');
        }

        $requestedStatus = $context['requested_status'] ?? null;
        if ($requestedStatus === EnrollmentStatusEnum::ACTIVE->value) {
            $this->moodle->enrollUser((int) $userId, (int) $courseId,
                strtotime((string) ($context['access_start_date'] ?? '')) ?: null,
                strtotime((string) ($context['access_end_date'] ?? '')) ?: null, $this->moodle->getDefaultRoleId());
        } elseif (in_array($requestedStatus, [
            EnrollmentStatusEnum::SUSPENDED->value,
            EnrollmentStatusEnum::EXPIRED->value,
            EnrollmentStatusEnum::CANCELLED->value,
        ], true)
        ) {
            $this->moodle->unenrollUser((int) $userId, (int) $courseId);
        } else {
            throw new UnrecoverableProvisioningException('Moodle access reconciliation requires manual action.');
        }

        return $references;
    }
}
