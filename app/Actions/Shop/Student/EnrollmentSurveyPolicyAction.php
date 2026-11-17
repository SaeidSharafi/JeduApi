<?php

declare(strict_types=1);

namespace App\Actions\Shop\Student;

use App\Enums\EnrollmentStatusEnum;
use App\Enums\EnrollmentSurveyStateEnum;
use App\Enums\Product\ProductableEnum;
use App\Models\Enrollment;

final readonly class EnrollmentSurveyPolicyAction
{
    public function supportsSurvey(Enrollment $enrollment): bool
    {
        $productableType = $enrollment->productDeliveryOption?->product?->productable_type;

        return is_string($productableType)
            && (ProductableEnum::tryFrom($productableType)?->supportsEnrollmentSurvey() ?? false);
    }

    public function allowsSurveyCompletion(Enrollment $enrollment): bool
    {
        return in_array($enrollment->enrollment_status, [
            EnrollmentStatusEnum::ACTIVE,
            EnrollmentStatusEnum::EXPIRED,
        ], true)
            && ($enrollment->access_start_date === null
                || $enrollment->access_start_date->lessThanOrEqualTo(today()))
            && $enrollment->revocation_status === null;
    }

    public function requiresCompletion(Enrollment $enrollment): bool
    {
        return $this->surveyState($enrollment)->locksResults();
    }

    public function surveyState(Enrollment $enrollment): EnrollmentSurveyStateEnum
    {
        if (! $this->supportsSurvey($enrollment) || ! (bool) $enrollment->productDeliveryOption?->survey_required) {
            return EnrollmentSurveyStateEnum::NOT_REQUIRED;
        }

        if ($enrollment->survey_completed_at !== null) {
            return EnrollmentSurveyStateEnum::COMPLETED;
        }

        return filled($enrollment->productDeliveryOption->survey_url)
            ? EnrollmentSurveyStateEnum::PENDING
            : EnrollmentSurveyStateEnum::AWAITING_SURVEY;
    }
}
