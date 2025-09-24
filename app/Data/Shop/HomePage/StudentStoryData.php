<?php

declare(strict_types=1);

namespace App\Data\Shop\HomePage;

use App\Data\Admin\MediaData;
use App\Models\StudentStory;
use Spatie\LaravelData\Data;

final class StudentStoryData extends Data
{
    /**
     * Create a StudentStoryData value object.
     *
     * Represents a student story for the shop home page.
     *
     * @param string $student_name Student's full name.
     * @param string|null $avatar_url URL to the student's avatar image, or null if none.
     * @param string $course_name Name of the course the student attended.
     * @param string $course_url URL to the course page.
     * @param string $story_text The student's story or testimonial text.
     * @param int $display_order Ordering weight used to sort stories (lower values appear first). Defaults to 0.
     */
    public function __construct(
        public string $student_name,
        public ?string $avatar_url,
        public string $course_name,
        public string $course_url,
        public string $story_text,
        public int $display_order = 0
    ) {}

    /**
     * Create a StudentStoryData DTO from a StudentStory model.
     *
     * Builds the DTO using the model's array representation and includes an `avatar_url`
     * set to the URL of the model's first media in the "avatar" collection, or null if none.
     *
     * @param StudentStory $story The StudentStory model to convert.
     * @return self
     */
    public static function fromModel(StudentStory $story): self
    {
        return self::from([
            ...$story->toArray(),
            'avatar_url' => $story->firstMedia('avatar')?->getUrl(),
        ]);
    }
}
