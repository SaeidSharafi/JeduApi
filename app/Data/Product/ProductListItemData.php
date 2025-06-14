<?php

namespace App\Data\Product;

use App\Contracts\ProductableContract;
use App\Contracts\ProductableDataContract;
use App\Data\Casts\MorphEnumCast;
use App\Data\Casts\ProductableCast;
use App\Data\Term\ShowTermData;
use App\Data\Term\TermListItemData;
use App\Data\Transformer\TranslatableEnumData;
use App\Data\Vendor\VendorListItemData;
use App\Data\Vendor\VendorShortListItemData;
use App\Enums\MorphTypeEnum;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

class ProductListItemData extends Data
{
    public function __construct(
        public int $id,
        public bool $is_visible,
        public ?string $short_description,
        public ?string $short_name,
        public ?string $name,
        public bool $is_featured,
        public VendorShortListItemData $vendor,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public MorphTypeEnum $productable_type,
        #[WithCast(ProductableCast::class, short: true)]
        #[MapOutputName('productable_data')]
        public ProductableDataContract $productable,
        public TermListItemData $term,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public \App\Enums\PublicationStatusEnum $status,
        public ?array $details_json
    )
    {
    }
}
