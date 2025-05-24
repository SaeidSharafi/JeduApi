<?php

declare(strict_types=1);

namespace App\Data\Category;

use App\Enums\PublicationStatusEnum;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class CreateCategoryData extends Data
{
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
        return [
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
            'name'             => ['required', 'string', 'max:191'],
            'status'           => ['required', Rule::enum(PublicationStatusEnum::class)],
            'description'      => ['nullable', 'string', 'max:65535'],
            'parent_id'        => ['nullable', 'integer', 'exists:categories,id'],
            'color_scheme'     => ['nullable', 'string'],
            'meta_title'       => ['nullable', 'string', 'max:191'],
            'meta_description' => ['nullable', 'string', 'max:65535'],
            'meta_keywords'    => ['nullable', 'string', 'max:65535'],
            'properties'       => ['nullable', 'array'],
            'additional_info'  => ['nullable', 'array'],
            'media'            => ['required', 'array'],
            'media.icon'       => ['nullable', 'integer', 'exists:media,id'],
            'media.image'      => ['nullable', 'integer', 'exists:media,id'],
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
                'description' => 'The meta title for SEO purposes.',
                'example'     => 'Web Development Courses',
            ],
            'meta_description' => [
                'description' => 'The meta description for SEO purposes.',
                'example'     => 'Explore our comprehensive web development courses.',
            ],
            'meta_keywords' => [
                'description' => 'The meta keywords for SEO purposes.',
                'example'     => 'web development, programming, coding',
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
                    'icon'  => 1, // Media ID for the icon
                    'image' => 2, // Media ID for the image
                ],
            ],
            'media.icon' => [
                'description' => 'The media ID for the category icon. (as an array)',
                'example'     => 1,
            ],
            'media.image' => [
                'description' => 'The media ID for the category image. (as an array)',
                'example'     => 2,
            ],
        ];
    }
}
