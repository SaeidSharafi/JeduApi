<?php

declare(strict_types=1);

namespace App\Data\Admin\Category;

use App\Enums\PublicationStatusEnum;
use App\Traits\ValidatesMetaTags;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class CreateCategoryData extends Data
{
    use ValidatesMetaTags;

    public function __construct(
        public string $name,
        public string $slug,
        #[WithCast(EnumCast::class)]
        public PublicationStatusEnum $status,
        public ?int $parent_id,
        public ?string $description,
        public ?string $color_scheme,
        public ?string $meta_title,
        public ?string $meta_description,
        public ?string $meta_keywords,
        public ?array $properties,
        public ?array $additional_info,
        public ?array $media = [],
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
                    Rule::unique('categories', 'slug')->where(function (Builder $query) {
                        $category = request()->route()->parameter('category');
                        if ($category && $category->id) {
                            $query->whereNot('id', $category->id);
                        }

                        return $query;
                    }),
                ],
                'name'                       => ['required', 'string', 'max:191'],
                'status'                     => ['required', Rule::enum(PublicationStatusEnum::class)],
                'description'                => ['nullable', 'string', 'max:65535'],
                'parent_id'                  => ['nullable', 'integer', 'exists:categories,id'],
                'color_scheme'               => ['nullable', 'string'],
                'properties'                 => ['nullable', 'array'],
                'additional_info'            => ['nullable', 'array'],
                'media'                      => ['required', 'array'],
                'media.icon'                 => ['nullable', 'integer', 'exists:media,id'],
                'media.image'                => ['nullable', 'integer', 'exists:media,id'],
                'media.educational_calendar' => ['nullable', 'integer', 'exists:media,id'],
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
            'name'             => __('validation.attributes.category.name'),
            'slug'             => __('validation.attributes.slug'),
            'status'           => __('validation.attributes.category.status'),
            'parent_id'        => __('validation.attributes.category.parent_id'),
            'description'      => __('validation.attributes.category.description'),
            'color_scheme'     => __('validation.attributes.category.color_scheme'),
            'meta_title'       => __('validation.attributes.meta_title'),
            'meta_description' => __('validation.attributes.meta_description'),
            'meta_keywords'    => __('validation.attributes.meta_keywords'),
            'properties'       => __('validation.attributes.properties'),
            'additional_info'  => __('validation.attributes.additional_info'),
            'media'            => __('validation.attributes.media.self'),
            'media.icon'       => __('validation.attributes.media.icon'),
            'media.image'      => __('validation.attributes.media.image'),
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
            'name' => [
                'description' => 'The name of the category.',
                'example'     => 'Web Development',
            ],
            'slug' => [
                'description' => 'The unique slug for the category.',
                'example'     => 'web-development',
            ],
            'status' => [
                'description' => 'The publication status of the category.',
                'example'     => PublicationStatusEnum::PUBLISHED->value,
            ],
            'parent_id' => [
                'description' => 'The ID of the parent category, if any.',
                'example'     => 1,
            ],
            'description' => [
                'description' => 'A brief description of the category.',
                'example'     => 'This category includes all courses related to web development.',
            ],
            'color_scheme' => [
                'description' => 'The color scheme for the category, used for UI representation.',
                'example'     => '#3490dc',
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
                'description' => 'Additional properties for the category, if any.',
                'example'     => ['difficulty' => 'beginner', 'language' => 'English'],
            ],
            'additional_info' => [
                'description' => 'Any additional information related to the category.',
                'example'     => ['created_by' => 'admin', 'created_at' => '2023-10-01'],
            ],
            'media' => [
                'description' => 'Media associated with the category.',
                'example'     => [
                    'icon'                 => 1, // Media ID for the icon
                    'image'                => 2, // Media ID for the image
                    'educational_calendar' => 3, // Media ID for the educational calendar file
                ],
            ],
            'media.icon' => [
                'description' => 'The media ID for the category icon.',
                'example'     => 1,
            ],
            'media.image' => [
                'description' => 'The media ID for the category image.',
                'example'     => 2,
            ],
            'media.educational_calendar' => [
                'description' => 'The media ID for the educational calendar.',
                'example'     => 3,
            ],
        ];
    }
}
