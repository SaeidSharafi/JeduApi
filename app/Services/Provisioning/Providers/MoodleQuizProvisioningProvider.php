<?php

declare(strict_types=1);

namespace App\Services\Provisioning\Providers;

use App\Contracts\Integrations\MoodleClientContract;
use App\Contracts\Provisioning\ProvisioningProvider;
use App\Contracts\Provisioning\RevocationProvider;
use App\Enums\ProvisioningProviderEnum;
use App\Exceptions\Integrations\UnrecoverableProvisioningException;
use App\Models\Enrollment;
use Illuminate\Support\Carbon;

final readonly class MoodleQuizProvisioningProvider implements ProvisioningProvider, RevocationProvider
{
    public function __construct(private MoodleClientContract $moodle) {}

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

    public function revoke(Enrollment $enrollment): array
    {
        if (! $this->moodle->isEnabled()) {
            throw new UnrecoverableProvisioningException('Moodle provider is disabled.');
        }

        $this->moodle->assertConfigured();
        $references = data_get($enrollment->provisioning_data, 'providers.moodle_quiz.data', []);
        $userId     = data_get($references, 'moodle_user_id');
        $courseId   = data_get($references, 'moodle_course_id');
        if (! is_numeric($userId) || ! is_numeric($courseId)) {
            throw new UnrecoverableProvisioningException('Moodle enrollment references are missing.');
        }

        $this->moodle->unenrollUser((int) $userId, (int) $courseId);

        return [
            'moodle_user_id'   => (int) $userId,
            'moodle_course_id' => (int) $courseId,
            'revoked_at'       => Carbon::now()->toISOString(),
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
