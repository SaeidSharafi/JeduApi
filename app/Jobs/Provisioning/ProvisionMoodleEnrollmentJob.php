<?php

declare(strict_types=1);

namespace App\Jobs\Provisioning;

use App\Enums\System\SettingKeyEnum;
use App\Jobs\Provisioning\Concerns\HandlesProvisioningStatus;
use App\Models\Enrollment;
use App\Models\Setting;
use App\Services\Integrations\MoodleService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

final class ProvisionMoodleEnrollmentJob implements ShouldQueue
{
    use Dispatchable;
    use HandlesProvisioningStatus;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly int $enrollmentId) {}

    public function handle(MoodleService $moodleService): void
    {
        $config = Setting::getValue(SettingKeyEnum::MOODLE);

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
        $courseId = data_get($details, 'moodle_course_id');
        if (! is_numeric($courseId)) {
            throw new RuntimeException('Moodle course id is missing from delivery option details.');
        }
        $courseId = (int) $courseId;

        $moodleService->setConfig($config);
        [$moodleUserId, $moodleUsername] = $moodleService->findOrCreateUser($enrollment->customer);
        $startDate                       = data_get($details, 'enrollment_start_date');
        $endDate                         = data_get($details, 'enrollment_end_date');

        $startTime = is_string($startDate) && strtotime($startDate) !== false ? strtotime($startDate) : null;
        $endTime   = is_string($endDate)   && strtotime($endDate)   !== false ? strtotime($endDate) : null;

        $moodleService->enrollUser($moodleUserId, $courseId, $startTime, $endTime, $config['default_role_id']);

        $this->markProvisioningSuccess($enrollment, 'moodle', [
            'moodle_user_id'   => $moodleUserId,
            'moodle_user_name' => $moodleUsername,
            'moodle_course_id' => $courseId,
            'login_path'       => $config['default_login_redirect_script'] ?? '/my',
        ]);
    }

    public function failed(Throwable $exception): void
    {
        $enrollment = $this->findEnrollment();
        if (! $enrollment) {
            return;
        }

        $this->markProvisioningFailure($enrollment, 'moodle', $exception->getMessage());
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
