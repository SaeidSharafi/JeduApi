<?php

declare(strict_types=1);

namespace App\Data\Shop\Student\Enrollment;

use App\Enums\EnrollmentSurveyStateEnum;
use Spatie\LaravelData\Data;

final class EnrollmentSurveyBlockData extends Data
{
    public function __construct(
        public EnrollmentSurveyStateEnum $state,
        public bool $results_locked,
        public ?string $url,
        public ?string $message,
    ) {}
}
