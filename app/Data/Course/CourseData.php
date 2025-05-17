<?php

declare(strict_types=1);

namespace App\Data\Course;

use App\Enums\CourseStatusEnum;
use Spatie\LaravelData\Attributes\Validation;
use Spatie\LaravelData\Data;

final class CourseData extends Data
{
    /**
     * slug
     * name
     * short_name
     * description
     * default_teacher_info
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
        public ?string $default_teacher_info,
        #[Validation\StringType, Validation\Max(191)]
        public ?string $meta_title,
        #[Validation\StringType, Validation\Max(191)]
        public ?string $meta_description,
        #[Validation\StringType, Validation\Max(191)]
        public ?string $meta_keywords,
        #[Validation\Enum(CourseStatusEnum::class)]
        #[WithCast(CourseStatusEnum::class)]
        public CourseStatusEnum $status
    ) {}
}
