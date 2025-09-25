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

    /**
     * @codeCoverageIgnore
     *
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return [
            'student_name' => [
                'description' => 'Name of the student.',
                'example'     => 'Ali Rezaei',
            ],
            'course_name' => [
                'description' => 'Name of the course.',
                'example'     => 'Advanced Mathematics',
            ],
            'course_url' => [
                'description' => 'URL of the course.',
                'example'     => 'https://jedu.ir/courses/advanced-math',
            ],
            'story_text' => [
                'description' => 'Text of the student story.',
                'example'     => 'This course helped me excel in university.',
            ],
            'avatar' => [
                'description' => 'Media ID for the student avatar.',
                'example'     => 123,
            ],
            'is_visible' => [
                'description' => 'Whether the story is visible.',
                'example'     => true,
            ],
            'display_order' => [
                'description' => 'Order for displaying the story.',
                'example'     => 1,
            ],
        ];
    }
}
