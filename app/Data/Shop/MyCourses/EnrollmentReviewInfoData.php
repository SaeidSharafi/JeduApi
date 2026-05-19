<?php

declare(strict_types=1);

namespace App\Data\Shop\MyCourses;

use Spatie\LaravelData\Data;

final class EnrollmentReviewInfoData extends Data
{
    public function __construct(
        public bool $has_reviewed,
        public ?array $review,
    ) {}
}
