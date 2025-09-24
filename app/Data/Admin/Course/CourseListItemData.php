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
use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

final class CourseListItemData extends Data implements ProductableDataContract, ReviewableDataContract
{
    #[WithTransformer(TranslatableEnumData::class)]
    public ProductableEnum $type = ProductableEnum::COURSE;

    public function __construct(
        public int $id,
        public string $slug,
        public string $full_name,
        public string $short_name,
        public ?string $thumbnail_url,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public CourseDifficultyLevelEnum $difficulty_level,
        public ?array $additional_info,
        public ?array $properties,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public PublicationStatusEnum $status,
        #[DataCollectionOf(CategoryListItemData::class)]
        public ?DataCollection $categories,
        #[DataCollectionOf(DigitalAssetListItemData::class)]
        #[MapInputName('digital_assets')]
        public ?DataCollection $digital_assets,
        public ?int $created_by,
        #[WithTransformer(\Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer::class, 'Y-m-d H:i:s')]
        public Verta $created_at,
        #[WithTransformer(\Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer::class, 'Y-m-d H:i:s')]
        public Verta $updated_at,
    ) {}
}
