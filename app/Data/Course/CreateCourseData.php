<?php

declare(strict_types=1);

namespace App\Data\Course;

use App\Enums\CourseDifficultyLevelEnum;
use App\Enums\PublicationStatusEnum;
use App\Rules\DigitalAssetIsAttachableRule;
use App\Traits\ValidatesMetaTags;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class CreateCourseData extends Data
{
    use ValidatesMetaTags;

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
        public array $categories,
        public array $digital_assets,
        public array $media = [],
    ) {}

    public static function rules(ValidationContext $context): array
    {
        return array_merge(
            [
                'slug' => [
                    'required',
                    'string',
                    'alpha_dash',
                    'max:191',
                    Rule::unique('courses', 'slug')->where(function (Builder $query) {
                        $course = request()->route()->parameter('course');
                        if ($course && $course->id) {
                            $query->whereNot('id', $course->id);
                        }

                        return $query;
                    }),
                ],
                'full_name'        => ['required', 'string', 'max:191'],
                'short_name'       => ['required', 'string', 'max:60'],
                'description'      => ['nullable', 'string', 'max:65535'],
                'duration'         => ['nullable', 'integer', 'min:1'],
                'difficulty_level' => [
                    'required', Rule::enum(CourseDifficultyLevelEnum::class),
                ],
                'career_prospects_text'   => ['nullable', 'string', 'max:65535'],
                'curriculum_summary_text' => ['nullable', 'string', 'max:65535'],
                'outcomes_json'           => ['required', 'array'],
                'default_teacher_info'    => ['nullable', 'string', 'max:1000'],
                'additional_info'         => ['nullable', 'array'],
                'properties'              => ['nullable', 'array'],
                'status'                  => ['required'],
                'categories'              => ['required', 'array'],
                'categories.*'            => ['required', 'integer', 'exists:categories,id'],
                'digital_assets'          => ['present', 'array', new DigitalAssetIsAttachableRule()],
                'media'                   => ['required', 'array'],
                'media.gallery'           => ['nullable', 'array'],
                'media.cover'             => ['required', 'array'],
                'media.video'             => ['nullable', 'array'],
                'media.cover.*'           => ['required', 'integer', 'exists:media,id'],
                'media.gallery.*'         => ['nullable', 'integer', 'exists:media,id'],
                'media.video.*'           => ['nullable', 'integer', 'exists:media,id'],
            ],
            self::metaTagValidationRules()
        );
    }

    /**
     * @codeCoverageIgnore
     *
     * @return array<string, string>
     */
    public static function attributes(...$args): array
    {
        return [
            'slug'                    => __('validation.attributes.slug'),
            'full_name'               => __('validation.attributes.course.full_name'),
            'short_name'              => __('validation.attributes.course.short_name'),
            'description'             => __('validation.attributes.description'),
            'duration'                => __('validation.attributes.course.duration'),
            'difficulty_level'        => __('validation.attributes.course.difficulty_level'),
            'career_prospects_text'   => __('validation.attributes.course.career_prospects_text'),
            'curriculum_summary_text' => __('validation.attributes.course.curriculum_summary_text'),
            'outcomes_json'           => __('validation.attributes.course.outcomes_json'),
            'default_teacher_info'    => __('validation.attributes.course.default_teacher_info'),
            'additional_info'         => __('validation.attributes.additional_info'),
            'meta_title'              => __('validation.attributes.meta_title'),
            'meta_description'        => __('validation.attributes.meta_description'),
            'meta_keywords'           => __('validation.attributes.meta_keywords'),
            'properties'              => __('validation.attributes.properties'),
            'status'                  => __('validation.attributes.status'),
            'categories'              => __('validation.attributes.categories'),
            'digital_assets'          => __('validation.attributes.digital_assets'),
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
                'required'    => true,
                'example'     => 'course-slug',
            ],
            'full_name' => [
                'description' => 'Full name of the course',
                'required'    => true,
                'example'     => 'Full Course Name',
            ],
            'short_name' => [
                'description' => 'Short name of the course',
                'required'    => true,
                'example'     => 'Short Course Name',
            ],
            'description' => [
                'description' => 'Description of the course',
                'required'    => false,
                'example'     => 'This is a course description',
            ],
            'duration' => [
                'description' => 'Duration of the course in minutes',
                'required'    => false,
                'example'     => 120,
            ],
            'difficulty_level' => [
                'description' => 'Difficulty level of the course',
                'required'    => true,
                'example'     => CourseDifficultyLevelEnum::BEGINNER->value,
            ],
            'career_prospects_text' => [
                'description' => 'Career prospects text of the course',
                'required'    => false,
                'example'     => 'Career prospects text',
            ],
            'curriculum_summary_text' => [
                'description' => 'Curriculum summary text of the course',
                'required'    => false,
                'example'     => 'Curriculum summary text',
            ],
            'outcomes_json' => [
                'description' => 'Outcomes JSON of the course',
                'required'    => false,
                'example'     => json_encode(['outcome1' => 'Text', 'outcome2' => 'Text']),
            ],
            'default_teacher_info' => [
                'description' => 'Default teacher info of the course',
                'required'    => false,
                'example'     => 'Default teacher info',
            ],
            'additional_info' => [
                'description' => 'Additional info of the course (JSON format)',
                'required'    => false,
                'example'     => json_encode(['info1', 'info2']),
            ],
            'meta_title' => [
                'description' => 'The meta title for the digital asset, used for SEO.',
                'example'     => 'Digital Asset Meta Title',
            ],
            'meta_description' => [
                'description' => 'The meta description for the digital asset, used for SEO.',
                'example'     => 'This is a meta description for the digital asset.',
            ],
            'meta_keywords' => [
                'description' => 'Meta keywords for the digital asset, used for SEO.',
                'example'     => 'meta keyword1, meta keyword2',
            ],
            'properties' => [
                'description' => 'Properties of the course (JSON format)',
                'example'     => json_encode(['property1', 'property2']),
            ],
            'status' => [
                'description' => 'Status of the course',
                'example'     => PublicationStatusEnum::DRAFT->value,
            ],
            'categories' => [
                'description' => 'Array of category ids for the course',
                'example'     => [1, 2, 3],
            ],
            'categories.*' => [
                'description' => 'Array of category ids for the course',
                'example'     => 1,
            ],
            'digital_assets' => [
                'description' => 'Array of digital asset ids for the course',
                'example'     => [1, 2, 3],
            ],
            'digital_assets.*' => [
                'description' => 'Array of digital asset ids for the course',
                'example'     => 1,
            ],
            'media' => [
                'description' => 'Media of the course',
            ],
            'media.gallery' => [
                'description' => 'media ids for gallery',
                'example'     => [1, 2, 3],
            ],
            'media.cover' => [
                'description' => 'media ids for cover',
                'example'     => [1],
            ],
            'media.video' => [
                'description' => 'media ids for video',
                'example'     => [1],
            ],
            'media.cover.*' => [
                'description' => 'Array of media ids for cover',
                'example'     => 1,
            ],
            'media.gallery.*' => [
                'description' => 'Array of media ids for gallery',
                'example'     => 1,
            ],
            'media.video.*' => [
                'description' => 'Array of media ids for video',
                'example'     => 1,
            ],
        ];
    }
}
