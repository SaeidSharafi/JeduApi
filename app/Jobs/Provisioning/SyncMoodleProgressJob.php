<?php

declare(strict_types=1);

namespace App\Jobs\Provisioning;

use App\Data\Shop\MyCourses\Blocks\LmsMoodleBlockData;
use App\Enums\System\SettingKeyEnum;
use App\Models\Enrollment;
use App\Models\Setting;
use App\Services\Integrations\MoodleService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

final class SyncMoodleProgressJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        private readonly int $enrollmentId,
        private readonly int $moodleCourseId,
        private readonly int $moodleUserId
    ) {}

    public function handle(MoodleService $moodleService): void
    {
        $enrollment = Enrollment::find($this->enrollmentId);
        if (! $enrollment) {
            return;
        }

        $config = Setting::getValue(SettingKeyEnum::MOODLE, config('services.moodle'));

        if (! is_array($config) || ! isset($config['base_url'], $config['token']) || ! is_string($config['base_url']) || ! is_string($config['token'])) {
            return;
        }

        if ($config['base_url'] === '' || $config['token'] === '') {
            return;
        }

        $moodleService->setConfig($config);

        $courseInfo       = $moodleService->getCourse($this->moodleCourseId);
        $isCompleted      = $moodleService->isCourseCompleted($this->moodleCourseId, $this->moodleUserId);
        $activityStatuses = $moodleService->getActivityCompletionStatus($this->moodleCourseId, $this->moodleUserId);
        $grades           = $moodleService->getGrades($this->moodleCourseId, $this->moodleUserId);

        $data               = LmsMoodleBlockData::from($courseInfo);
        $data->completed    = $isCompleted;
        $data->course_grade = $enrollment->survey_completed_at ? data_get($grades, 'course_grade') : null;

        foreach ($data->activities as $activity) {
            $activity->state = data_get($activityStatuses, "{$activity->cid}.state", 0);
            $activity->grade = $enrollment->survey_completed_at
                ? data_get($grades, "activities.{$activity->cid}")
                : null;
        }

        $enrollment->forceFill([
            'provisioning_data->providers->moodle->data->course_info' => $data,
        ])->saveQuietly();
    }

    public function backoff(): array
    {
        return [60, 180];
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Failed to sync Moodle progress after 3 attempts.', [
            'enrollment_id'    => $this->enrollmentId,
            'moodle_course_id' => $this->moodleCourseId,
            'moodle_user_id'   => $this->moodleUserId,
            'error'            => $exception->getMessage(),
        ]);

        RateLimiter::clear(
            "throttle:moodle-sync:{$this->enrollmentId}:{$this->moodleCourseId}:{$this->moodleUserId}"
        );
    }
}
