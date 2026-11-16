<?php

declare(strict_types=1);

namespace App\Actions\Shop\Student;

use App\Data\Shop\Student\Blocks\LmsMoodleBlockData;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final readonly class RedactStudentQuizResultsAction
{
    public function __construct(private EnrollmentSurveyPolicyAction $surveyPolicy) {}

    /**
     * @param  array<int, array<string, mixed>|LmsMoodleBlockData>  $quizzes
     * @return array<int, array<string, mixed>|LmsMoodleBlockData>
     */
    public function handle(User $user, array $quizzes): array
    {
        $enrollments = $user->enrollments()
            ->with('productDeliveryOption.product')
            ->get();

        foreach ($quizzes as &$quiz) {
            $courseId = $this->courseIdFromUrl(data_get($quiz, 'course_url'));
            if ($courseId === null || ! $this->hasLockedMatchingEnrollment($enrollments, $courseId)) {
                continue;
            }

            if ($quiz instanceof LmsMoodleBlockData) {
                $quiz               = clone $quiz;
                $quiz->course_grade = null;
                $activities         = $quiz->activities;

                foreach ($activities as &$activity) {
                    if (is_array($activity)) {
                        if (array_key_exists('grade', $activity)) {
                            $activity['grade'] = null;
                        }
                        if (array_key_exists('score', $activity)) {
                            $activity['score'] = null;
                        }
                    } elseif (is_object($activity)) {
                        $activity = clone $activity;
                        if (property_exists($activity, 'grade')) {
                            $activity->grade = null;
                        }
                        if (property_exists($activity, 'score')) {
                            $activity->score = null;
                        }
                    }
                }
                unset($activity);

                $quiz->activities = $activities;

                continue;
            }

            $quiz['course_grade'] = null;
            foreach ($quiz['activities'] ?? [] as $index => $activity) {
                if (array_key_exists('grade', $activity)) {
                    $quiz['activities'][$index]['grade'] = null;
                }
                if (array_key_exists('score', $activity)) {
                    $quiz['activities'][$index]['score'] = null;
                }
            }
        }
        unset($quiz);

        return $quizzes;
    }

    /** @param Collection<int, Enrollment> $enrollments */
    private function hasLockedMatchingEnrollment(Collection $enrollments, int $courseId): bool
    {
        return $enrollments->contains(function (Enrollment $enrollment) use ($courseId): bool {
            if (! $this->surveyPolicy->requiresCompletion($enrollment)) {
                return false;
            }

            $option          = $enrollment->productDeliveryOption;
            $optionCourseIds = array_filter([
                data_get($option->details_json, 'moodle_course_id'),
                data_get($option->details_json, 'moodle_quiz_course_id'),
                data_get($enrollment->provisioning_data, 'providers.moodle.data.moodle_course_id'),
                data_get($enrollment->provisioning_data, 'providers.moodle_quiz.data.moodle_course_id'),
            ], fn (mixed $id): bool => is_numeric($id) && (int) $id > 0);

            return in_array($courseId, array_map('intval', $optionCourseIds), true);
        });
    }

    private function courseIdFromUrl(mixed $url): ?int
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $id = $query['id'] ?? null;

        return is_numeric($id) && (int) $id > 0 ? (int) $id : null;
    }
}
