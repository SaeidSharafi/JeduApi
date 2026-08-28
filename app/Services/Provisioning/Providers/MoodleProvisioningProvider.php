<?php

declare(strict_types=1);

namespace App\Services\Provisioning\Providers;

use App\Contracts\Provisioning\ProvisioningProvider;
use App\Enums\ProvisioningProviderEnum;
use App\Exceptions\Integrations\UnrecoverableProvisioningException;
use App\Models\Enrollment;
use App\Services\Integrations\MoodleService;
use Illuminate\Support\Carbon;

final readonly class MoodleProvisioningProvider implements ProvisioningProvider
{
    public function __construct(private MoodleService $moodle) {}

    public function provider(): ProvisioningProviderEnum
    {
        return ProvisioningProviderEnum::MOODLE;
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

        $this->moodle->getCourse((int) $courseId);
        $this->moodle->enrollUser($userId, (int) $courseId, $startTime, $endTime, $this->moodle->getDefaultRoleId());

        return [
            'moodle_user_id'   => $userId,
            'moodle_user_name' => $username,
            'moodle_course_id' => (int) $courseId,
            'login_path'       => $this->moodle->getLoginPath(),
            'provisioned_at'   => Carbon::now()->toISOString(),
        ];
    }
}
