<?php

declare(strict_types=1);

namespace App\Data\Shop\Student\Enrollment;

use App\Data\Shop\Student\Review\ReviewData;
use Spatie\LaravelData\Data;

final class EnrollmentReviewInfoData extends Data
{
    public function __construct(
        public bool $has_reviewed,
        public ?ReviewData $review,
    ) {}
}
