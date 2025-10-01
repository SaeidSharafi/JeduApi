<?php

declare(strict_types=1);

namespace App\Actions\Admin\RelatedProduct;

use App\Enums\Product\RelationTypeEnum;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

final class DeleteRelatedProductAction
{
    public function handle(Product $product, RelationTypeEnum $relationType, ?Product $relatedProduct = null): void
    {
        DB::table('related_products')
            ->where('product_id', $product->id)
            ->when($relatedProduct,fn($q) => $q->where('related_product_id', $relatedProduct->id))
            ->where('relation_type', $relationType->value)
            ->delete();
    }
}
