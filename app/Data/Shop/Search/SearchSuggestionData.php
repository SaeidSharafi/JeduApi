<?php

declare(strict_types=1);

namespace App\Data\Shop\Search;

use App\Data\Transformer\TranslatableEnumData;
use App\Enums\Product\ProductableEnum;
use App\Models\Product;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;

final class SearchSuggestionData extends Data
{
    public function __construct(
        public string $name,
        public string $slug,
        #[WithTransformer(TranslatableEnumData::class)]
        public ProductableEnum $product_type,
    ) {}

    public static function fromProduct(Product $product): self
    {
        return new self(
            name: $product->name,
            slug: $product->slug,
            product_type: ProductableEnum::from($product->productable_type),
        );
    }
}
