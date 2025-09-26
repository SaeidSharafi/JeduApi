<?php

namespace App\Data\Shop\Product\Course;

use App\Data\Admin\Category\CategoryListItemData;
use App\Data\Admin\DigitalAsset\DigitalAssetListItemData;
use App\Data\Shop\Product\CategoryCardData;
use App\Data\Shop\Product\DigitalAssetPublicData;
use App\Data\Transformer\TranslatableEnumData;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\CourseDifficultyLevelEnum;
use App\Models\Product;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

class CourseDetailData extends Data
{
    public function __construct(
        public string $slug,
        public string $full_name,
        public string $short_name,
        public ?string $description,
        public ?int $duration,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
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
        public ?array $details,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public PublicationStatusEnum $status,
        #[DataCollectionOf(CategoryCardData::class)]
        public ?DataCollection $categories,
        #[DataCollectionOf(DigitalAssetPublicData::class)]
        #[MapInputName('digitalAssets')]
        public ?DataCollection $digital_assets,
        public array $media = [],
    ) {
    }

    public static function fromModel(Product $product): self
    {
        return self::from(
            [
                'slug'                    => $product->slug,
                'full_name'               => $product->name ?? $product->productable->full_name,
                'short_name'              => $product->short_name,
                'description'             => $product->productable->description,
                'duration'                => $product->productable->duration,
                'difficulty_level'        => $product->productable->difficulty_level,
                'career_prospects_text'   => $product->productable->career_prospects_text,
                'curriculum_summary_text' => $product->productable->curriculum_summary_text,
                'outcomes_json'           => $product->productable->outcomes_json,
                'default_teacher_info'    => $product->productable->default_teacher_info,
                'additional_info'         => $product->productable->additional_info,
                'meta_title'              => $product->productable->meta_title,
                'meta_description'        => $product->productable->meta_description,
                'meta_keywords'           => $product->productable->meta_keywords,
                'properties'              => $product->productable->properties,
                'details'                 => $product->details_json,
                'status'                  => $product->status,
                'categories'              => $product->categories,
                'digitalAssets'           => $product->productable->digitalAssets,
                'media'                   => $product->productable->getAllMedia(urlOnly: true),
            ]
        );
    }
}
