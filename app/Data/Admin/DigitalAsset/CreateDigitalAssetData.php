<?php

declare(strict_types=1);

namespace App\Data\Admin\DigitalAsset;

use App\Enums\Content\PublicationStatusEnum;
use App\Enums\CourseDifficultyLevelEnum;
use App\Helpers\JalaliDateHelper;
use App\Rules\ValidNormalizedJalaliDateRule;
use App\Traits\ValidatesMetaTags;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

final class CreateDigitalAssetData extends Data
{
    use ValidatesMetaTags;

    public function __construct(
        public string $short_name,
        public string $full_name,
        public string $slug,
        public ?string $description,
        public ?string $version,
        public bool $is_attachable_to_course,
        public string $difficulty_level,
        #[WithCast(EnumCast::class)]
        public PublicationStatusEnum $status,
        public ?array $faq,
        public ?int $created_by,
        public ?string $keywords,
        public ?string $meta_title,
        public ?string $meta_description,
        public ?string $meta_keywords,
        #[WithCast(DateTimeInterfaceCast::class, 'Y-m-d H:i:s')]
        public ?Carbon $published_at,
        public ?int $page_count,
        public ?int $duration_seconds,
        public array $categories,
        public array $attachments,
        public array $media = [],
    ) {}

    public static function prepareForPipeline(array $properties): array
    {
        return JalaliDateHelper::toGregorian($properties, [
            'published_at' => 'Y-m-d H:i:s',
        ]);
    }

    /**
     * @codeCoverageIgnore
     * Get the validation rules for the data.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function rules(): array
    {

        return array_merge(
            [
                'short_name' => ['required', 'string', 'max:100'],
                'full_name'  => ['required', 'string', 'max:191'],
                'slug'       => [
                    'required',
                    'string',
                    'alpha_dash',
                    'max:191',
                    Rule::unique('digital_assets', 'slug')->where(function (Builder $query) {
                        $asset = request()->route()->parameter('digital_asset');
                        if ($asset && $asset->id) {
                            $query->whereNot('id', $asset->id);
                        }

                        return $query;
                    }),
                ],
                'description'             => ['nullable', 'string'],
                'version'                 => ['nullable', 'string', 'max:50'],
                'is_attachable_to_course' => ['nullable', 'boolean'],
                'difficulty_level'        => [
                    'required', Rule::enum(CourseDifficultyLevelEnum::class),
                ],
                'status'              => ['required', Rule::enum(PublicationStatusEnum::class)],
                'created_by'          => ['nullable', 'integer', 'exists:staff,id'],
                'keywords'            => ['nullable', 'string', 'max:255'],
                'published_at'        => ['bail', 'nullable', new ValidNormalizedJalaliDateRule, 'date_format:Y-m-d H:i:s'],
                'page_count'          => ['nullable', 'integer', 'min:0'],
                'duration_seconds'    => ['nullable', 'integer', 'min:0'],
                'faq'                 => ['nullable', 'array'],
                'faq.*.question'      => ['required', 'string', 'max:255'],
                'faq.*.answer'        => ['required', 'string'],
                'categories'          => ['required', 'array'],
                'categories.*'        => ['required', 'integer', 'exists:categories,id'],
                'attachments'         => ['array'],
                'attachments.main'    => ['required', 'integer', 'exists:media,id'],
                'attachments.preview' => ['nullable', 'integer', 'exists:media,id'],
                'media'               => ['required', 'array'],
                'media.gallery'       => ['nullable', 'array'],
                'media.cover'         => ['required', 'array'],
                'media.video'         => ['nullable', 'array'],
                'media.cover.*'       => ['required', 'integer', 'exists:media,id'],
                'media.gallery.*'     => ['nullable', 'integer', 'exists:media,id'],
                'media.video.*'       => ['nullable', 'integer', 'exists:media,id'],
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
            'short_name'              => __('validation.attributes.digital_asset.short_name'),
            'full_name'               => __('validation.attributes.digital_asset.full_name'),
            'version'                 => __('validation.attributes.digital_asset.version'),
            'is_attachable_to_course' => __('validation.attributes.digital_asset.is_attachable_to_course'),
            'published_at'            => __('validation.attributes.digital_asset.published_at'),
            'keywords'                => __('validation.attributes.digital_asset.keywords'),
            'page_count'              => __('validation.attributes.digital_asset.page_count'),
            'duration_seconds'        => __('validation.attributes.digital_asset.duration_seconds'),
            'attachments.main'        => __('validation.attributes.digital_asset.attachments.main'),
            'attachments.preview'     => __('validation.attributes.digital_asset.attachments.preview'),

        ];
    }

    /**
     * @codeCoverageIgnore
     *
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'faq.*.question.required' => __('validation.custom.digital_asset.faq.question.required'),
            'faq.*.answer.required'   => __('validation.custom.digital_asset.faq.answer.required'),
        ];
    }

    /**
     * @codeCoverageIgnore
     *
     * Get the validation rules for the data.
     *
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return [
            'short_name' => [
                'description' => 'The short name of the digital asset.',
                'example'     => 'Digital Asset',
            ],
            'full_name' => [
                'description' => 'The full name of the digital asset.',
                'example'     => 'Digital Asset Full Name',
            ],
            'slug' => [
                'description' => 'A unique slug for the digital asset, used in URLs.',
                'example'     => 'digital-asset-name',
            ],
            'description' => [
                'description' => 'A brief description of the digital asset.',
                'example'     => 'This is a description of the digital asset.',
            ],
            'version' => [
                'description' => 'The version of the digital asset.',
                'example'     => '1.0.0',
            ],
            'is_attachable_to_course' => [
                'description' => 'Indicates if this asset can be attached to a course.',
                'example'     => true,
            ],
            'status' => [
                'description' => 'The publication status of the digital asset.',
                'example'     => PublicationStatusEnum::PUBLISHED->value,
            ],
            'page_count' => [
                'description' => 'The number of pages in the digital asset, if applicable.',
                'example'     => 100,
            ],
            'duration_seconds' => [
                'description' => 'The duration of the digital asset in seconds, if applicable.',
                'example'     => 3600,
            ],
            'created_by' => [
                'description' => 'The ID of the staff who created this digital asset.',
                'example'     => 1,
            ],
            'published_at' => [
                'description' => 'The date and time when the digital asset was published.',
                'example'     => '1403-10-01 12:00:00',
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
                'description' => 'Keywords associated with the digital asset for search optimization.',
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
                'description' => 'An array of category IDs to which the digital asset belongs.',
                'example'     => [1, 2, 3],
            ],
            'categories.*' => [
                'description' => 'An array of category IDs to which the digital asset belongs.',
                'example'     => 1,
            ],
            'attachments.main' => [
                'description' => 'The main attachment for the digital asset, typically a file ID.',
                'example'     => 1,
            ],
            'attachments.preview' => [
                'description' => 'An optional preview attachment for the digital asset, typically a file ID.',
                'example'     => 2,
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
