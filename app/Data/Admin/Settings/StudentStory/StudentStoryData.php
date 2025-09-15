<?php

declare(strict_types=1);

namespace App\Data\Admin\Settings\StudentStory;

use App\Data\Admin\MediaData;
use App\Models\StudentStory;
use Spatie\LaravelData\Data;

final class StudentStoryData extends Data
{
    public function __construct(
        public int $id,
        public string $student_name,
        public string $course_name,
        public string $course_url,
        public string $story_text,
        public bool $is_visible,
        public ?MediaData $avatar = null,
        public int $display_order = 0
    ) {}

    public static function fromModel(StudentStory $story): self
    {
        return self::from([
            ...$story->toArray(),
            'avatar' => $story->firstMedia('avatar'),
        ]);
    }
}
