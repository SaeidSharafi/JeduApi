<?php

declare(strict_types=1);

namespace App\Data\Course;

use App\Enums\CourseDifficultyLevelEnum;
use App\Enums\PublicationStatusEnum;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class CreateCourseData extends Data
{
    /**
     * slug
     * full_name // Changed from name
     * short_name
     * description
     * sample_certificate_image_url
     * total_video_duration_minutes
     * difficulty_level
     * career_prospects_text
     * curriculum_summary_text
     * outcomes_json
     * default_teacher_info
     * additional_info
     * meta_title
     * meta_description
     * meta_keywords
     * properties
     * status
     */
    public function __construct(
        public string $slug,
        public string $full_name,
        public string $short_name,
        public ?string $description,
        public ?int $duration,
        #[WithCast(EnumCast::class)]
        public CourseDifficultyLevelEnum $difficulty_level,
        public ?string $career_prospects_text,
        public ?string $curriculum_summary_text,
        public ?array $outcomes_json,
        public ?string $default_teacher_info,
        public ?array $additional_info,
        public ?string $meta_title,
        public ?string $meta_description,
        public ?string $meta_keywords,
        public ?array $properties,
        #[WithCast(EnumCast::class)]
        public PublicationStatusEnum $status,
        public array $categories = [],
        public array $media = [],
    ) {}

    public static function rules(ValidationContext $context): array
    {
        return [
            'slug' => [
                'required',
                'string',
                'alpha_dash',
                'max:191',
                Rule::unique('courses', 'slug')->where(function ($query) {
                    $course = request()->route()->parameter('course');
                    if ($course && $course->id) {
                        $query->whereNot('id', $course->id);
                    }

                    return $query;
                }),
            ],
            'full_name' => ['required', 'string', 'max:191'],
            'short_name' => ['required', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:65535'],
            'duration' => ['nullable', 'integer', 'min:1'],
            'difficulty_level' => [
                'required', Rule::enum(CourseDifficultyLevelEnum::class),
            ],
            'career_prospects_text' => ['nullable', 'string', 'max:65535'],
            'curriculum_summary_text' => ['nullable', 'string', 'max:65535'],
            'outcomes_json' => ['nullable', 'array'],
            'default_teacher_info' => ['nullable', 'string', 'max:1000'],
            'additional_info' => ['nullable', 'array'],
            'meta_title' => ['nullable', 'string', 'max:191'],
            'meta_description' => ['nullable', 'string', 'max:65535'],
            'meta_keywords' => ['nullable', 'string', 'max:65535'],
            'properties' => ['nullable', 'array'],
            'status' => ['required', Rule::enum(PublicationStatusEnum::class)],
            'categories' => ['nullable', 'array'],
            'categories.*' => [
                'nullable',
                'integer',
                'exists:categories,id',
            ],
            'media' => ['required', 'array'],
            'media.gallery' => ['nullable', 'array'],
            'media.cover' => ['nullable', 'array'],
            'media.video' => ['nullable', 'array'],
            'media.cover.*' => ['nullable', 'integer', 'exists:media,id'],
            'media.gallery.*' => ['nullable', 'integer', 'exists:media,id'],
            'media.video.*' => ['nullable', 'integer', 'exists:media,id'],
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
            'slug' => [
                'description' => 'Slug of the course',
                'required' => true,
                'example' => 'course-slug',
            ],
            'full_name' => [
                'description' => 'Full name of the course',
                'required' => true,
                'example' => 'Full Course Name',
            ],
            'short_name' => [
                'description' => 'Short name of the course',
                'required' => true,
                'example' => 'Short Course Name',
            ],
            'description' => [
                'description' => 'Description of the course',
                'required' => false,
                'example' => 'This is a course description',
            ],
            'duration' => [
                'description' => 'Duration of the course in minutes',
                'required' => false,
                'example' => 120,
            ],
            'difficulty_level' => [
                'description' => 'Difficulty level of the course',
                'required' => true,
                'example' => CourseDifficultyLevelEnum::BEGINNER->value,
            ],
            'career_prospects_text' => [
                'description' => 'Career prospects text of the course',
                'required' => false,
                'example' => 'Career prospects text',
            ],
            'curriculum_summary_text' => [
                'description' => 'Curriculum summary text of the course',
                'required' => false,
                'example' => 'Curriculum summary text',
            ],
            'outcomes_json' => [
                'description' => 'Outcomes JSON of the course',
                'required' => false,
                'example' => json_encode(['outcome1' => 'Text', 'outcome2' => 'Text']),
            ],
            'default_teacher_info' => [
                'description' => 'Default teacher info of the course',
                'required' => false,
                'example' => 'Default teacher info',
            ],
            'additional_info' => [
                'description' => 'Additional info of the course',
                'required' => false,
                'example' => json_encode(['info1', 'info2']),
            ],
            'meta_title' => [
                'description' => 'Meta title of the course',
                'required' => false,
                'example' => 'Meta title',
            ],
            'meta_description' => [
                'description' => 'Meta description of the course',
                'required' => false,
                'example' => 'Meta description',
            ],
            'meta_keywords' => [
                'description' => 'Meta keywords of the course',
                'required' => false,
                'example' => 'Meta keywords',
            ],
            'properties' => [
                'description' => 'Properties of the course',
                'required' => false,
                'example' => json_encode(['property1', 'property2']),
            ],
            'status' => [
                'description' => 'Status of the course',
                'required' => true,
                'example' => PublicationStatusEnum::DRAFT->value,
            ],
            'media' => [
                'description' => 'Media of the course',
                'required' => true,
                'gallery' => [
                    'example' => [1],
                    'description' => 'Array of media ids for gallery',
                ],
                'cover' => [
                    'example' => [1],
                    'description' => 'Array of media ids for gallery',
                ],
                'video' => [
                    'example' => [1],
                    'description' => 'Array of media ids for video',
                ],
            ],
        ];
    }
}
