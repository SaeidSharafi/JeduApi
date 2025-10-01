<?php

declare(strict_types=1);

namespace App\Actions\Admin\RelatedProduct;

use App\Data\Admin\Product\RelatedProductSyncData;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

final class CreateRelatedProductAction
{
    public function handle(Product $product, RelatedProductSyncData $data): void
    {
        if (in_array($product->id, $data->product_ids)) {
            abort(422, 'A product cannot be related to itself.');
        }

        DB::transaction(function () use ($product, $data) {
            $product->relatedProducts()
                ->wherePivot('relation_type', $data->relation_type->value)
                ->detach();

            $syncData = [];
            foreach ($data->product_ids as $relatedProductId) {
                $syncData[$relatedProductId] = [
                    'relation_type' => $data->relation_type->value,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ];
            }

            $product->relatedProducts()->attach($syncData);
        });
    }
}
