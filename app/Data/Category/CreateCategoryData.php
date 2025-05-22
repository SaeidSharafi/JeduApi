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
        public ?int $parent_id = null,
        public ?string $description = null,
        public ?string $color_scheme = null,
        public ?string $meta_title = null,
        public ?string $meta_description = null,
        public ?string $meta_keywords = null,
        public ?array $properties = null,
        public ?array $additional_info = null,
        public ?array $media = [],
    ) {
    }

    public static function rules(ValidationContext $context): array
    {
        return [
            'slug'             => [
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
}
