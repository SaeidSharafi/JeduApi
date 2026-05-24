<?php

declare(strict_types=1);

namespace App\Data\Admin\Settings\StudentStory;

use App\Data\Admin\Category\CategoryListItemData;
use App\Data\Admin\Course\CourseListItemData;
use App\Data\Admin\MediaData;
use App\Models\StudentStory;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Attributes\DataCollectionOf;
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
        public bool $is_featured = false,
        #[DataCollectionOf(CategoryListItemData::class)]
        public ?Collection $categories = null,
        #[DataCollectionOf(CourseListItemData::class)]
        public ?Collection $courses = null,
        public int $display_order = 0
    ) {}

    public static function fromModel(StudentStory $story): self
    {
        return self::from([
            ...$story->toArray(),
            'avatar'     => $story->firstMedia('avatar'),
            'categories' => $story->categories,
            'courses'    => $story->courses,
        ]);
    }
}
