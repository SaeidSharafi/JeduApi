<?php

declare(strict_types=1);

namespace App\Actions\Admin\RelatedProduct;

use App\Data\Admin\Product\RelatedProductSyncData;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CreateRelatedProductAction
{
    public function __construct(private DeleteRelatedProductAction $deleteAction) {}

    public function handle(Product $product, RelatedProductSyncData $data): void
    {
        if (in_array($product->id, $data->product_ids, true)) {
            throw ValidationException::withMessages([
                'product_ids' => [__('validation.custom.product.related_product_cannot_be_self')],
            ]);
        }

        DB::transaction(function () use ($product, $data): void {
            $this->deleteAction->handle($product, $data->relation_type);
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
