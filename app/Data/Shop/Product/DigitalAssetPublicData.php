<?php

declare(strict_types=1);

namespace App\Data\Shop\Product;

use App\Contracts\ProductableDataContract;
use App\Contracts\ReviewableDataContract;
use App\Data\Admin\Category\CategoryListItemData;
use App\Data\Transformer\TranslatableEnumData;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Product\ProductableEnum;
use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

final class DigitalAssetPublicData extends Data implements ProductableDataContract, ReviewableDataContract
{
    #[WithTransformer(TranslatableEnumData::class)]
    public ProductableEnum $type = ProductableEnum::DIGITAL_ASSET;

    public function __construct(
        public string $name,
        public string $slug,
        public ?string $description,
        public ?string $version,
        public ?string $keywords,
        public ?string $meta_title,
        public ?string $meta_description,
        public ?string $meta_keywords,
        public ?int $page_count,
        public ?int $duration_seconds,
        public ?string $thumbnail_url,
    ) {}
}
