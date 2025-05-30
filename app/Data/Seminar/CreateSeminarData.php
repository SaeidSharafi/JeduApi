<?php

declare(strict_types=1);

namespace App\Data\Seminar;

use App\Enums\CourseDifficultyLevelEnum;
use App\Enums\PublicationStatusEnum;
use App\Rules\DigitalAssetIsAttachableRule;
use App\Traits\ValidatesMetaTags;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

final class CreateSeminarData extends Data
{
    use ValidatesMetaTags;

    public function __construct(
        public string $full_name,
        public string $short_name,
        public ?string $subtitle,
        public ?string $slug,
        #[WithCast(EnumCast::class)]
        public PublicationStatusEnum $status,
        #[WithCast(EnumCast::class)]
        public CourseDifficultyLevelEnum $level,
        public bool $provides_certificate,
        public ?string $description,
        public ?string $learning_objectives,
        public ?string $target_audience,
        public ?string $prerequisites,
        public ?string $promo_video_external_url,
        public ?string $estimated_duration_desc,
        public ?array $faq,
        public ?string $keywords,
        public ?string $meta_title,
        public ?string $meta_description,
        public ?string $meta_keywords,
        public array $categories,
        public array $digital_assets = [],
        public array $media = [],
    ) {}

    public static function rules(): array
    {
        return array_merge([
            'full_name'  => ['required', 'string', 'max:255'],
            'short_name' => ['required', 'string', 'max:255'],
            'subtitle'   => ['nullable', 'string', 'max:255'],
            'slug'       => [
                'required',
                'string',
                'alpha_dash',
                'max:191',
                Rule::unique('seminars', 'slug')->where(function (Builder $query) {
                    $seminar = request()->route()->parameter('seminar');
                    if ($seminar && $seminar->id) {
                        $query->whereNot('id', $seminar->id);
                    }

                    return $query;
                }),
            ],
            'status'                   => ['required', Rule::enum(PublicationStatusEnum::class)],
            'level'                    => ['nullable', Rule::enum(CourseDifficultyLevelEnum::class)],
            'provides_certificate'     => ['boolean'],
            'description'              => ['nullable', 'string'],
            'learning_objectives'      => ['nullable', 'string'],
            'target_audience'          => ['nullable', 'string'],
            'prerequisites'            => ['nullable', 'string'],
            'promo_video_external_url' => ['nullable', 'url'],
            'estimated_duration_desc'  => ['nullable', 'string'],
            'faq'                      => ['nullable', 'array'],
            'faq.*.question'           => ['required', 'string', 'max:255'],
            'faq.*.answer'             => ['required', 'string'],
            'keywords'                 => ['nullable', 'string'],
            'categories'               => ['required', 'array'],
            'categories.*'             => ['required', 'integer', 'exists:categories,id'],
            'digital_assets'           => ['required', 'array', new DigitalAssetIsAttachableRule()],
            'media'                    => ['required', 'array'],
            'media.gallery'            => ['nullable', 'array'],
            'media.cover'              => ['required', 'array'],
            'media.video'              => ['nullable', 'array'],
            'media.cover.*'            => ['required', 'integer', 'exists:media,id'],
            'media.gallery.*'          => ['nullable', 'integer', 'exists:media,id'],
            'media.video.*'            => ['nullable', 'integer', 'exists:media,id'],
        ],
            self::metaTagValidationRules()
        );
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
                'example'     => 'course-slug',
            ],
            'full_name' => [
                'description' => 'Full name of the course',
                'example'     => 'Full Course Name',
            ],
            'short_name' => [
                'description' => 'Short name of the course',
                'example'     => 'Short Course Name',
            ],
            'subtitle' => [
                'description' => 'Subtitle of the course',
                'example'     => 'This is a subtitle for the course',
            ],
            'status' => [
                'description' => 'Publication status of the course',
                'example'     => 'published',
            ],
            'level' => [
                'description' => 'Difficulty level of the course',
                'example'     => CourseDifficultyLevelEnum::BEGINNER->value,
            ],
            'provides_certificate' => [
                'description' => 'Indicates if the course provides a certificate upon completion',
                'example'     => true,
            ],
            'description' => [
                'description' => 'Detailed description of the course',
                'example'     => 'This is a detailed description of the course.',
            ],
            'learning_objectives' => [
                'description' => 'Learning objectives of the course',
                'example'     => 'By the end of this course, you will be able to...',
            ],
            'target_audience' => [
                'description' => 'Target audience for the course',
                'example'     => 'This course is designed for...',
            ],
            'prerequisites' => [
                'description' => 'Prerequisites for the course',
                'example'     => 'Before taking this course, you should have...',
            ],
            'promo_video_external_url' => [
                'description' => 'External URL for the promotional video of the course',
                'example'     => 'https://example.com/promo-video',
            ],
            'estimated_duration_desc' => [
                'description' => 'Estimated duration of the course in a human-readable format',
                'example'     => 'Approximately 3 hours',
            ],
            'faq' => [
                'description' => 'Frequently Asked Questions for the course',
                'example'     => [
                    ['question' => 'What is the course about?', 'answer' => 'This course covers...'],
                    ['question' => 'Who is the instructor?', 'answer' => 'The instructor is...'],
                ],
            ],
            'faq.*.question' => [
                'description' => 'Question in the FAQ',
                'example'     => 'What is the course about?',
            ],
            'faq.*.answer' => [
                'description' => 'Answer to the FAQ question',
                'example'     => 'This course covers...',
            ],
            'keywords' => [
                'description' => 'Keywords for the course, used for SEO.',
                'example'     => 'keyword1, keyword2',
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
            'categories' => [
                'description' => 'Array of category ids for the course',
                'example'     => [1, 2, 3],
            ],
            'categories.*' => [
                'description' => 'Array of category ids for the course',
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
