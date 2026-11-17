<?php

declare(strict_types=1);

namespace App\Data\Shop\Student\Enrollment;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

final class EnrollmentGradeReportData extends Data
{
    public function __construct(
        public string $status,
        public string $status_translated,
        /** @var DataCollection<int, EnrollmentGradeReportItemData> */
        #[DataCollectionOf(EnrollmentGradeReportItemData::class)]
        public DataCollection $items,
    ) {}
}
