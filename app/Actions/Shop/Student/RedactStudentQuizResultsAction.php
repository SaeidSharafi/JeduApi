<?php

declare(strict_types=1);

namespace App\Actions\Shop\Student;

use App\Enums\Product\ProductableEnum;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final readonly class RedactStudentQuizResultsAction
{
    /** @param array<int, array<string, mixed>> $quizzes
     * @return array<int, array<string, mixed>>
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
            $option          = $enrollment->productDeliveryOption;
            $productableType = $option->product?->productable_type;
            $isEligible      = in_array($productableType, [
                ProductableEnum::COURSE->value,
                ProductableEnum::SEMINAR->value,
            ], true);
            $optionCourseIds = array_filter([
                data_get($option->details_json, 'moodle_course_id'),
                data_get($option->details_json, 'moodle_quiz_course_id'),
                data_get($enrollment->provisioning_data, 'providers.moodle.data.moodle_course_id'),
                data_get($enrollment->provisioning_data, 'providers.moodle_quiz.data.moodle_course_id'),
            ], fn (mixed $id): bool => is_numeric($id) && (int) $id > 0);

            return $isEligible
                && in_array($courseId, array_map('intval', $optionCourseIds), true)
                && $option->survey_required
                && $enrollment->survey_completed_at === null;
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
