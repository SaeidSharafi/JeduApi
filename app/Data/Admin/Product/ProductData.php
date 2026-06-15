<?php

declare(strict_types=1);

namespace App\Data\Admin\Product;

use App\Contracts\ProductableDataContract;
use App\Data\Admin\Term\ShowTermData;
use App\Data\Casts\ProductableCast;
use App\Data\Transformer\TranslatableEnumData;
use App\Enums\System\MorphTypeEnum;
use App\Models\Product;
use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

final class ProductData extends Data
{
    public function __construct(
        public int $id,
        public int $vendor_id,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        #[MapOutputName('product_type')]
        public MorphTypeEnum $productable_type,
        #[WithCast(ProductableCast::class, short: true)]
        #[MapOutputName('product_data')]
        public ProductableDataContract $productable,
        public ShowTermData $term,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public \App\Enums\Content\PublicationStatusEnum $status,
        public bool $is_visible,
        public ?string $short_description,
        public ?string $short_name,
        public ?string $name,
        public bool $is_featured,
        public ?array $details_json,
        public ?Verta $event_start_at = null,
        public ?Verta $event_ended_at = null,
    ) {}

    public static function fromModel(Product $product): self
    {
        return self::from([
            ...$product->toArray(),
            'productable'    => $product->productable,
            'event_start_at' => $product->event_start_at,
            'event_ended_at' => $product->event_ended_at,
        ]);
    }
}
