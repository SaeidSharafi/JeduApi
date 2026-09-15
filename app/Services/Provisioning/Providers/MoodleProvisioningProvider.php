<?php

declare(strict_types=1);

namespace App\Services\Provisioning\Providers;

use App\Contracts\Integrations\MoodleClientContract;
use App\Contracts\Provisioning\ProvisioningProvider;
use App\Contracts\Provisioning\RevocationProvider;
use App\Enums\EnrollmentStatusEnum;
use App\Enums\ProvisioningProviderEnum;
use App\Exceptions\Integrations\UnrecoverableProvisioningException;
use App\Models\Enrollment;
use Illuminate\Support\Carbon;

final readonly class MoodleProvisioningProvider implements ProvisioningProvider, RevocationProvider
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
        $requestedStatus = $context['requested_status'] ?? null;
        if ($requestedStatus === EnrollmentStatusEnum::ACTIVE->value) {
            $references = $this->moodleReferences($enrollment);
            $this->moodle->enrollUser($references['moodle_user_id'], $references['moodle_course_id'],
                strtotime((string) ($context['access_start_date'] ?? '')) ?: null,
                strtotime((string) ($context['access_end_date'] ?? '')) ?: null, $this->moodle->getDefaultRoleId());

            return data_get($enrollment->provisioning_data, 'providers.moodle.data', []);
        }

        if (! in_array($requestedStatus, [
            EnrollmentStatusEnum::SUSPENDED->value,
            EnrollmentStatusEnum::EXPIRED->value,
            EnrollmentStatusEnum::CANCELLED->value,
        ], true)
        ) {
            throw new UnrecoverableProvisioningException('Moodle access reconciliation requires manual action.');
        }

        $this->unenroll($enrollment);

        return data_get($enrollment->provisioning_data, 'providers.moodle.data', []);
    }

    public function revoke(Enrollment $enrollment): array
    {
        if (! $this->moodle->isEnabled()) {
            throw new UnrecoverableProvisioningException('Moodle provider is disabled.');
        }

        $this->moodle->assertConfigured();
        $references = $this->unenroll($enrollment);

        return [
            'moodle_user_id'   => $references['moodle_user_id'],
            'moodle_course_id' => $references['moodle_course_id'],
            'revoked_at'       => Carbon::now()->toISOString(),
        ];
    }

    /**
     * The single Moodle "remove external access" primitive shared by
     * reconciliation and revocation.
     *
     * @return array{moodle_user_id: int, moodle_course_id: int}
     */
    private function unenroll(Enrollment $enrollment): array
    {
        $references = $this->moodleReferences($enrollment);
        $this->moodle->unenrollUser($references['moodle_user_id'], $references['moodle_course_id']);

        return $references;
    }

    /** @return array{moodle_user_id: int, moodle_course_id: int} */
    private function moodleReferences(Enrollment $enrollment): array
    {
        $references = data_get($enrollment->provisioning_data, 'providers.moodle.data', []);
        $userId     = data_get($references, 'moodle_user_id');
        $courseId   = data_get($references, 'moodle_course_id');
        if (! is_numeric($userId) || ! is_numeric($courseId)) {
            throw new UnrecoverableProvisioningException('Moodle enrollment references are missing.');
        }

        return [
            'moodle_user_id'   => (int) $userId,
            'moodle_course_id' => (int) $courseId,
        ];
    }
}
