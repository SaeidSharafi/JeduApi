<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Data\Shop\MyCourses\Blocks\LmsMoodleBlockData;
use App\Enums\System\SettingKeyEnum;
use App\Models\Enrollment;
use App\Models\Setting;

final readonly class MoodleProgressRefresher
{
    public function __construct(
        private int $enrollmentId,
        private int $moodleCourseId,
        private int $moodleUserId
    ) {}

    public function __invoke(): array
    {
        /** @var MoodleService $moodleService */
        $moodleService = app(MoodleService::class);

        $config = Setting::getValue(SettingKeyEnum::MOODLE, config('services.moodle'));
        $moodleService->setConfig($config);

        $courseInfo       = $moodleService->getCourse($this->moodleCourseId);
        $isCompleted      = $moodleService->isCourseCompleted($this->moodleCourseId, $this->moodleUserId);
        $activityStatuses = $moodleService->getActivityCompletionStatus($this->moodleCourseId, $this->moodleUserId);
        $grades           = $moodleService->getGrades($this->moodleCourseId, $this->moodleUserId);

        $enrollment = Enrollment::find($this->enrollmentId);

        $data               = LmsMoodleBlockData::from($courseInfo);
        $data->completed    = $isCompleted;
        $data->course_grade = $enrollment->survey_completed_at ? data_get($grades, 'course_grade') : null;

        foreach ($data->activities as $activity) {
            $activity->state = data_get($activityStatuses, "$activity->cid.state", 0);
            $activity->grade = $enrollment->survey_completed_at
                ? data_get($grades, "activities.$activity->cid")
                : null;
        }

        if ($enrollment) {
            $enrollment->forceFill([
                'provisioning_data->providers->moodle->data->course_info' => $data,
            ])->saveQuietly();
        }

        return $data->toArray();
    }
}
