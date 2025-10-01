<?php

declare(strict_types=1);

namespace App\Actions\Admin\RelatedProduct;

use App\Enums\Product\RelationTypeEnum;
use App\Models\Product;

final class DeleteRelatedProductAction
{
    public function handle(Product $product, Product $relatedProduct, RelationTypeEnum $relationType): void
    {
        $product->relatedProducts()
            ->wherePivot('relation_type', $relationType)
            ->detach($relatedProduct->id);
    }
}
