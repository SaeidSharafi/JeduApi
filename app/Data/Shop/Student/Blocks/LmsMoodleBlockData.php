<?php

declare(strict_types=1);

namespace App\Data\Shop\Student\Blocks;

use Spatie\LaravelData\Data;

final class LmsMoodleBlockData extends Data
{
    public function __construct(
        public bool $visible,
        public string $name,
        public ?string $course_url,
        public bool $completed,
        public ?string $course_grade = null,
        public array $activities = [],
    ) {}
}
