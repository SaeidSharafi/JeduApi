<?php

declare(strict_types=1);

namespace App\Services\Provisioning\Providers;

use App\Contracts\Provisioning\ProvisioningProvider;
use App\Enums\ProvisioningProviderEnum;
use App\Exceptions\Integrations\UnrecoverableProvisioningException;
use App\Models\Enrollment;
use App\Services\Integrations\MoodleService;

final readonly class MoodleQuizProvisioningProvider implements ProvisioningProvider
{
    public function __construct(private MoodleService $moodle) {}

    public function provider(): ProvisioningProviderEnum
    {
        return ProvisioningProviderEnum::MOODLE_QUIZ;
    }

    public function supportsAccessReconciliation(): bool
    {
        return false;
    }

    public function provision(Enrollment $enrollment): array
    {
        if (! $this->moodle->isEnabled()) {
            throw new UnrecoverableProvisioningException('Moodle provider is disabled.');
        }

        $this->moodle->assertConfigured();
        $enrollment = $enrollment->fresh(['customer', 'productDeliveryOption']);
        if (! $enrollment || ! $this->isApplicable($enrollment)) {
            throw new UnrecoverableProvisioningException('Moodle Quiz provider is not applicable to this enrollment.');
        }
        $courseId = data_get($enrollment->productDeliveryOption?->details_json, 'moodle_quiz_course_id');
        if (! is_numeric($courseId)) {
            throw new UnrecoverableProvisioningException(__('messages.provisioning.moodle_quiz_course_id_missing'));
        }
        $courseId = (int) $courseId;

        [$moodleUserId, $moodleUsername] = $this->moodle->findOrCreateUser($enrollment->customer);
        $this->moodle->enrollUser($moodleUserId, $courseId, null, null, $this->moodle->getDefaultRoleId());

        return [
            'moodle_user_id'   => $moodleUserId,
            'moodle_username'  => $moodleUsername,
            'moodle_course_id' => $courseId,
        ];
    }

    private function isApplicable(Enrollment $enrollment): bool
    {
        return collect($enrollment->provisioning_plan['providers'] ?? [])->contains(
            fn (array $provider): bool => ($provider['provider'] ?? null) === $this->provider()->value
                && ($provider['applicable'] ?? false)                     === true,
        );
    }
}
