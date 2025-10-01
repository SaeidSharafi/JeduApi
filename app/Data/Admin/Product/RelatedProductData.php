<?php

declare(strict_types=1);

namespace App\Data\Admin\Product;

use App\Data\Transformer\TranslatableEnumData;
use App\Enums\Product\RelationTypeEnum;
use App\Models\Product;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

final class RelatedProductData extends Data
{
    public function __construct(
        public int $product_id,
        public int $related_product_id,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public RelationTypeEnum $relation_type,
        public ProductListItemData $related_product,
        public ?string $created_at = null,
    ) {}

    public static function fromModel(Product $product): self
    {
        return new self(
            product_id: $product->pivot->product_id,
            related_product_id: $product->pivot->related_product_id,
            relation_type: RelationTypeEnum::from($product->pivot->relation_type),
            related_product: ProductListItemData::from($product),
            created_at: $product->pivot->created_at?->toISOString(),
        );
    }
}
