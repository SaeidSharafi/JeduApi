<?php

declare(strict_types=1);

namespace App\Jobs\Provisioning;

use App\Models\Enrollment;
use App\Services\Integrations\MoodleService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * Syncs Moodle course progress back into the local enrollment record.
 *
 * This is a background sync task, not a provisioning task. Provisioning
 * runs exclusively through ProvisionEnrollmentProviderJob and the provider
 * adapters; this job only refreshes progress data into
 * provisioning_data.providers.<key>.sync.
 */
final class SyncMoodleProgressJob implements ShouldQueue
{
    use \Illuminate\Foundation\Queue\Queueable;

    public int $tries = 3;

    public function __construct(
        private readonly int $enrollmentId,
        private readonly int $moodleCourseId,
        private readonly int $moodleUserId,
        private readonly string $providerKey = 'moodle',
    ) {}

    public function handle(MoodleService $moodleService): void
    {
        $enrollment = Enrollment::find($this->enrollmentId);
        if (! $enrollment) {
            return;
        }

        // isReady() = isEnabled() && validateConfig() — replaces 8 lines of raw config checks.
        if (! $moodleService->isReady()) {
            return;
        }

        $courseInfo       = $moodleService->getCourse($this->moodleCourseId);
        $isCompleted      = $moodleService->isCourseCompleted($this->moodleCourseId, $this->moodleUserId);
        $activityStatuses = $moodleService->getActivityCompletionStatus($this->moodleCourseId, $this->moodleUserId);
        $grades           = $moodleService->getGrades($this->moodleCourseId, $this->moodleUserId);

        $activities = [];
        foreach ($courseInfo->activities as $activity) {
            $cmid         = $activity->cid;
            $activities[] = [
                'cmid'  => $cmid,
                'name'  => $activity->name,
                'type'  => $activity->type,
                'url'   => $activity->url,
                'state' => (int) data_get($activityStatuses, "{$cmid}.state", 0),
                'score' => $enrollment->survey_completed_at
                    ? data_get($grades, "activities.{$cmid}")
                    : null,
                'timecompleted' => data_get($activityStatuses, "{$cmid}.timecompleted"),
            ];
        }

        $syncData = [
            'synced_at'    => now()->toIso8601String(),
            'completed'    => $isCompleted,
            'course_grade' => $enrollment->survey_completed_at
                ? data_get($grades, 'course_grade')
                : null,
            'activities' => $activities,
        ];

        $enrollment->forceFill([
            "provisioning_data->providers->{$this->providerKey}->sync" => $syncData,
        ])->saveQuietly();
    }

    /**
     * @return array<int, int>
     */
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
            'provider_key'     => $this->providerKey,
            'error'            => $exception->getMessage(),
        ]);

        RateLimiter::clear(
            "throttle:moodle-sync:{$this->enrollmentId}:{$this->moodleCourseId}:{$this->moodleUserId}"
        );
    }
}
