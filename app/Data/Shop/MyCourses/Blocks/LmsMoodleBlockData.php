<?php

declare(strict_types=1);

namespace App\Data\Shop\MyCourses\Blocks;

use Spatie\LaravelData\Data;

final class LmsMoodleBlockData extends Data
{
    public function __construct(
        public ?string $course_url,
        public array $quizzes,
    ) {}
}
