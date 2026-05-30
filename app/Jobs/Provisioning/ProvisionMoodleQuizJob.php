<?php

declare(strict_types=1);

namespace App\Jobs\Provisioning;

use App\Enums\System\SettingKeyEnum;
use App\Jobs\Provisioning\Concerns\HandlesProvisioningStatus;
use App\Models\Enrollment;
use App\Services\Integrations\MoodleService;
use App\Services\SettingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

final class ProvisionMoodleQuizJob implements ShouldQueue
{
    use Dispatchable;
    use HandlesProvisioningStatus;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly int $enrollmentId) {}

    public function handle(MoodleService $moodleService, SettingsService $settings): void
    {
        $config = $settings->get(SettingKeyEnum::MOODLE);

        if (! ($config['enabled'] ?? false)) {
            return;
        }
        if (empty($config['base_url']) || empty($config['token'])) {
            throw new RuntimeException('Moodle configuration is missing base_url or token.');
        }

        $enrollment = $this->findEnrollment();
        if (! $enrollment) {
            return;
        }

        $details  = $enrollment->productDeliveryOption?->details_json ?? [];
        $courseId = data_get($details, 'moodle_quiz_course_id');
        if (! is_numeric($courseId)) {
            throw new RuntimeException('Moodle quiz course id is missing from delivery option details.');
        }
        $courseId = (int) $courseId;

        [$moodleUserId, $moodleUsername] = $moodleService->findOrCreateUser($enrollment->customer);
        $roleId                          = (int) ($config['default_role_id'] ?? 5);
        $moodleService->enrollUser($moodleUserId, $courseId, null, null, $roleId);

        $this->markProvisioningSuccess($enrollment, 'moodle_quiz', [
            'moodle_user_id'   => $moodleUserId,
            'moodle_username'  => $moodleUsername,
            'moodle_course_id' => $courseId,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        $enrollment = $this->findEnrollment();
        if (! $enrollment) {
            return;
        }

        $this->markProvisioningFailure($enrollment, 'moodle_quiz', $exception->getMessage());
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 180, 600];
    }

    private function findEnrollment(): ?Enrollment
    {
        return Enrollment::query()
            ->with(['customer', 'productDeliveryOption'])
            ->find($this->enrollmentId);
    }
}
