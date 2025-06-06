<?php

namespace App\Data\Product;

use App\Contracts\ProductableContract;
use App\Contracts\ProductableDataContract;
use App\Data\Casts\MorphEnumCast;
use App\Data\Casts\ProductableCast;
use App\Data\Term\ShowTermData;
use App\Data\Transformer\TranslatableEnumData;
use App\Enums\MorphTypeEnum;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

class ProductData extends Data
{
    public function __construct(
        public int $id,
        public int $vendor_id,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        #[MapOutputName('product_type')]
        public MorphTypeEnum $productable_type,
        #[WithCast(ProductableCast::class)]
        #[MapOutputName('product_data')]
        public ProductableDataContract $productable,
        public ShowTermData $term,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public \App\Enums\PublicationStatusEnum $status,
        public bool $is_visible,
        public ?string $short_description,
        public ?string $short_name,
        public ?string $name,
        public bool $is_featured,
        public ?array $details_json
    )
    {
    }
}
