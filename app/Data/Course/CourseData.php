<?php

declare(strict_types=1);

namespace App\Data\Course;

use App\Enums\CourseDifficultyLevelEnum;
use App\Enums\CourseStatusEnum;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\Validation;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class CourseData extends Data
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
        public CourseStatusEnum $status,
        public array $media = [],
    ) {

    }

    public static function rules(ValidationContext $context): array
    {
        return [
            'slug'                    => [
                'required',
                'string',
                'alpha_dash',
                'max:191',
                Rule::unique('courses', 'slug')->where(function ($query) use ($context) {
                    if (data_get($context->fullPayload, 'id')) {
                        $query->whereNot('id', data_get($context->fullPayload, 'id'));
                    }
                    return $query;
                })
            ],
            'full_name'               => ['required', 'string', 'max:191'],
            'short_name'              => ['required', 'string', 'max:60'],
            'description'             => ['nullable', 'string', 'max:65535'],
            'duration'                => ['nullable', 'integer', 'min:1'],
            'difficulty_level'        => [
                'required', Rule::enum(CourseDifficultyLevelEnum::class)
            ],
            'career_prospects_text'   => ['nullable', 'string', 'max:65535'],
            'curriculum_summary_text' => ['nullable', 'string', 'max:65535'],
            'outcomes_json'           => ['nullable', 'array'],
            'default_teacher_info'    => ['nullable', 'string', 'max:1000'],
            'additional_info'         => ['nullable', 'array'],
            'meta_title'              => ['nullable', 'string', 'max:191'],
            'meta_description'        => ['nullable', 'string', 'max:65535'],
            'meta_keywords'           => ['nullable', 'string', 'max:65535'],
            'properties'              => ['nullable', 'array'],
            'status'                  => ['required', Rule::enum(CourseStatusEnum::class)],
            'media'                   => ['required', 'array'],
            'media.gallery'           => ['nullable', 'array'],
            'media.cover'             => ['nullable', 'array'],
            'media.video'             => ['nullable', 'array'],
            'media.cover.*'             => ['nullable', 'integer', 'exists:media,id'],
            'media.gallery.*'         => ['nullable', 'integer', 'exists:media,id'],
            'media.video.*'           => ['nullable', 'integer', 'exists:media,id'],
        ];
    }

}
