<?php

namespace App\Data\Course;

use App\Enums\CourseStatusEnum;
use Spatie\LaravelData\Data;
use \Spatie\LaravelData\Attributes\Validation;
class CourseData extends Data
{
    /**
     * slug
     * name
     * short_name
     * description
     * default_teahcer_info
     * meta_title
     * meta_description
     * meta_keywords
     * status
     */
    public function __construct(
        #[Validation\AlphaDash, Unique('courses', 'slug')]
        public string $slug,
        #[Validation\Required, Validation\StringType, Validation\Max(191)]
        public string $name,
        #[Validation\Required, Validation\StringType, Validation\Max(60)]
        public string $short_name,
        #[Validation\StringType, Validation\Max(1000)]
        public ?string $description,
        #[Validation\StringType, Validation\Max(1000)]
        public ?string $default_teahcer_info,
        #[Validation\StringType, Validation\Max(191)]
        public ?string $meta_title,
        #[Validation\StringType, Validation\Max(191)]
        public ?string $meta_description,
        #[Validation\StringType, Validation\Max(191)]
        public ?string $meta_keywords,
        #[Validation\Enum(CourseStatusEnum::class)]
        #[WithCast(CourseStatusEnum::class)]
        public CourseStatusEnum $status
    )
    {
    }
}
