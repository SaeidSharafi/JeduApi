<?php

declare(strict_types=1);

namespace App\Data\Shop\HomePage;

use App\Models\StudentStory;
use Spatie\LaravelData\Data;

final class StudentStoryData extends Data
{
    public function __construct(
        public string $student_name,
        public ?string $avatar_url,
        public string $course_name,
        public string $course_url,
        public string $story_text,
        public int $display_order = 0
    ) {}
}
