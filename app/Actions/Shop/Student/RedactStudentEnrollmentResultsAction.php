<?php

declare(strict_types=1);

namespace App\Actions\Shop\Student;

use App\Models\Enrollment;

final readonly class RedactStudentEnrollmentResultsAction
{
    public function __construct(private EnrollmentSurveyPolicyAction $surveyPolicy) {}

    public function handle(Enrollment $enrollment): Enrollment
    {
        if (! $this->surveyPolicy->requiresCompletion($enrollment)) {
            return $enrollment;
        }

        $provisioningData = $enrollment->provisioning_data;

        if ($provisioningData === null) {
            return $enrollment;
        }

        foreach (['moodle', 'moodle_quiz'] as $provider) {
            if (! isset($provisioningData['providers'][$provider]['sync'])) {
                continue;
            }

            $provisioningData['providers'][$provider]['sync']['course_grade'] = null;
            foreach ($provisioningData['providers'][$provider]['sync']['activities'] ?? [] as $index => $activity) {
                if (array_key_exists('score', $activity)) {
                    $provisioningData['providers'][$provider]['sync']['activities'][$index]['score'] = null;
                }
                if (array_key_exists('grade', $activity)) {
                    $provisioningData['providers'][$provider]['sync']['activities'][$index]['grade'] = null;
                }
            }
        }

        $enrollment->setAttribute('provisioning_data', $provisioningData);

        return $enrollment;
    }
}
