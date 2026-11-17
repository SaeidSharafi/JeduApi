<?php

declare(strict_types=1);

namespace App\Data\Shop\Student\Enrollment;

use Spatie\LaravelData\Data;

final class EnrollmentGradeReportItemData extends Data
{
    public function __construct(
        public string $name,
        public string $label,
        public ?float $value,
        public ?float $pass_grade,
        public ?bool $is_passed,
    ) {}
}
