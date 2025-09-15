<?php

declare(strict_types=1);

namespace App\Data\Admin\Settings\StudentStory;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class StudentStoryCreateData extends Data
{
    public function __construct(
        public string $student_name,
        public string $course_name,
        public string $course_url,
        public string $story_text,
        public ?int $avatar,
        public bool $is_visible,
        public int $display_order = 0
    ) {}

    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'student_name'  => ['required', 'string', 'max:255'],
            'course_name'   => ['required', 'string', 'max:255'],
            'course_url'    => ['required', 'url', 'max:255'],
            'story_text'    => ['required', 'string'],
            'avatar'        => ['nullable', 'integer', 'exists:media,id'],
            'is_visible'    => ['required', 'boolean'],
            'display_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
