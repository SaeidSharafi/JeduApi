<?php

declare(strict_types=1);

namespace App\Data\Seminar;

use App\Contracts\ProductableDataContract;
use App\Data\Category\CategoryListItemData;
use App\Data\DigitalAsset\DigitalAssetListItemData;
use App\Data\Transformer\TranslatableEnumData;
use App\Enums\CourseDifficultyLevelEnum;
use App\Enums\PublicationStatusEnum;
use App\Traits\ValidatesMetaTags;
use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

final class ShowSeminarData extends Data implements ProductableDataContract
{
    use ValidatesMetaTags;

    public function __construct(
        public int $id,
        public string $full_name,
        public string $short_name,
        public ?string $subtitle,
        public ?string $slug,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public PublicationStatusEnum $status,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public CourseDifficultyLevelEnum $level,
        public bool $provides_certificate,
        public ?string $description,
        public ?string $learning_objectives,
        public ?string $target_audience,
        public ?string $prerequisites,
        public ?string $promo_video_external_url,
        public ?string $estimated_duration_desc,
        public array $faq,
        public ?string $keywords,
        public ?string $meta_title,
        public ?string $meta_description,
        public ?string $meta_keywords,
        #[DataCollectionOf(CategoryListItemData::class)]
        public ?DataCollection $categories,
        #[DataCollectionOf(DigitalAssetListItemData::class)]
        #[MapInputName('digitalAssets')]
        public ?DataCollection $digital_assets,
        public array $media,
        #[WithTransformer(\Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer::class, 'Y-m-d H:i:s')]
        public Verta $created_at,
        #[WithTransformer(\Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer::class, 'Y-m-d H:i:s')]
        public Verta $updated_at,
    ) {}
}
