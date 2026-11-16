<?php

declare(strict_types=1);

namespace App\Actions\Shop\Student;

use App\Enums\EnrollmentStatusEnum;
use App\Enums\Product\ProductableEnum;
use App\Models\Enrollment;

final readonly class EnrollmentSurveyPolicyAction
{
    public function supportsSurvey(Enrollment $enrollment): bool
    {
        return in_array($enrollment->productDeliveryOption?->product?->productable_type, [
            ProductableEnum::COURSE->value,
            ProductableEnum::SEMINAR->value,
        ], true);
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
        return $this->supportsSurvey($enrollment)
            && (bool) $enrollment->productDeliveryOption->survey_required
            && $enrollment->survey_completed_at === null;
    }
}
