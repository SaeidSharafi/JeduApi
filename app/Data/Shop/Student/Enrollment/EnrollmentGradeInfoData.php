<?php

declare(strict_types=1);

namespace App\Data\Shop\Student\Enrollment;

use App\Enums\EnrollmentGradeStateEnum;
use Spatie\LaravelData\Data;

final class EnrollmentGradeInfoData extends Data
{
    public function __construct(
        public EnrollmentGradeStateEnum $state,
        public ?EnrollmentGradeReportData $report,
    ) {}
}
