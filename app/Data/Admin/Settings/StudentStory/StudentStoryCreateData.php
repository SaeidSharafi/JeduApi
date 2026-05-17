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
        public bool $is_featured = false,
        public array $categories = [],
        public array $courses = [],
        public int $display_order = 0
    ) {}

    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'student_name'  => ['required', 'string', 'max:255'],
            'course_name'   => ['required', 'string', 'max:255'],
            'course_url'    => ['required', 'string', 'max:255'],
            'story_text'    => ['required', 'string'],
            'avatar'        => ['nullable', 'integer', 'exists:media,id'],
            'is_visible'    => ['required', 'boolean'],
            'is_featured'   => ['required', 'boolean'],
            'categories'    => ['sometimes', 'array'],
            'categories.*'  => ['integer', 'exists:categories,id'],
            'courses'       => ['sometimes', 'array'],
            'courses.*'     => ['integer', 'exists:courses,id'],
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
            'is_featured' => [
                'description' => 'Whether the story is featured.',
                'example'     => false,
            ],
            'categories' => [
                'description' => 'Array of category IDs associated with the story.',
                'example'     => [1, 2, 3],
            ],
            'categories.*' => [
                'description' => 'Individual category ID.',
                'example'     => 1,
            ],
            'courses' => [
                'description' => 'Array of course IDs associated with the story.',
                'example'     => [10, 20],
            ],
            'courses.*' => [
                'description' => 'Individual course ID.',
                'example'     => 10,
            ],
            'display_order' => [
                'description' => 'Order for displaying the story.',
                'example'     => 1,
            ],
        ];
    }
}
