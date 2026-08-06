<?php

declare(strict_types=1);

namespace App\Actions\Admin\Product;

use App\Data\Admin\Product\ProductUpdateData;
use App\Events\ProductAvailabilityCacheInvalidated;
use App\Events\ProductCacheInvalidated;
use App\Events\ProductSearchIndexInvalidated;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use SmartCache\Facades\SmartCache;

final readonly class UpdateProductAction
{
    public function handle(ProductUpdateData $data, Product $product): Product
    {
        // Serialize publish-status mutations per productable so concurrent updates
        // cannot race the single-published-shell invariant. The partial unique index
        // remains the hard DB backstop.
        $lockKey = "publish_productable_{$product->productable_type}_{$product->productable_id}";

        $product = SmartCache::lock($lockKey, 15)->block(5, function () use ($data, $product): Product {
            return DB::transaction(function () use ($data, $product): Product {
                $product->update($data->except('categories')->toArray());
                $product->categories()->sync($data->categories);
                $product->refresh();

                return $product;
            });
        });

        ProductCacheInvalidated::dispatch($product->id);
        ProductAvailabilityCacheInvalidated::dispatch([$product->id]);
        ProductSearchIndexInvalidated::dispatch([$product->id]);

        return $product;
    }
}
