<?php

declare(strict_types=1);

namespace App\Data\Admin\Category;

use App\Data\Admin\MediaData;
use App\Data\Transformer\TranslatableEnumData;
use App\Enums\PublicationStatusEnum;
use App\Models\Category;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

final class ShowCategoryData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
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
    ) {}

    public static function fromModel(Category $category): self
    {
        $icon                = null;
        $image               = null;
        $educationalCalendar = null;
        if ($category->relationLoaded('media')) {
            $icon = $category->firstMedia('icon')
                ? MediaData::fromModel($category->firstMedia('icon'), 'icon')
                : null;
            $image = $category->firstMedia('image')
                ? MediaData::fromModel($category->firstMedia('image'), 'image')
                : null;
            $educationalCalendar = $category->firstMedia('educational_calendar')
                ? MediaData::fromModel($category->firstMedia('educational_calendar'), 'educational_calendar')
                : null;
        }

        return new self(
            id: $category->id,
            name: $category->name,
            slug: $category->slug,
            status: $category->status,
            parent_id: $category->parent_id,
            description: $category->description,
            color_scheme: $category->color_scheme,
            meta_title: $category->meta_title,
            meta_description: $category->meta_description,
            meta_keywords: $category->meta_keywords,
            properties: $category->properties,
            additional_info: $category->additional_info,
            media: [
                'icon'                 => $icon?->toArray(),
                'image'                => $image?->toArray(),
                'educational_calendar' => $educationalCalendar?->toArray(),
            ],
        );
    }
}
