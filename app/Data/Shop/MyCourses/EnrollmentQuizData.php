<?php

declare(strict_types=1);

namespace App\Data\Shop\MyCourses;

use Spatie\LaravelData\Data;

final class EnrollmentQuizData extends Data
{
    public function __construct(
        public int $cmid,
        public string $name,
        public string $type,
        public string $url,
        public int $state,
        public ?string $score = null,
        public ?int $timecompleted = null,
    ) {}
}
