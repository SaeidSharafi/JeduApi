<?php

declare(strict_types=1);

namespace App\Data\Admin\Course;

use App\Contracts\ProductableDataContract;
use App\Contracts\ReviewableDataContract;
use App\Data\Admin\Category\CategoryListItemData;
use App\Data\Admin\DigitalAsset\DigitalAssetListItemData;
use App\Data\Transformer\TranslatableEnumData;
use App\Enums\CourseDifficultyLevelEnum;
use App\Enums\ProductableEnum;
use App\Enums\PublicationStatusEnum;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

final class ShowCourseData extends Data implements ProductableDataContract, ReviewableDataContract
{
    #[WithTransformer(TranslatableEnumData::class)]
    public ProductableEnum $type = ProductableEnum::COURSE;

    public function __construct(
        public int $id,
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
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public PublicationStatusEnum $status,
        #[DataCollectionOf(CategoryListItemData::class)]
        public ?DataCollection $categories,
        #[DataCollectionOf(DigitalAssetListItemData::class)]
        #[MapInputName('digitalAssets')]
        public ?DataCollection $digital_assets,
        public array $media = [],
    ) {}
}
