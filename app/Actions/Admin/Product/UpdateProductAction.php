<?php

declare(strict_types=1);

namespace App\Actions\Admin\Product;

use App\Data\Admin\Product\ProductUpdateData;
use App\Events\ProductAvailabilityCacheInvalidated;
use App\Events\ProductCacheInvalidated;
use App\Events\ProductSearchIndexInvalidated;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

final readonly class UpdateProductAction
{
    public function handle(ProductUpdateData $data, Product $product): Product
    {
        $product = DB::transaction(function () use ($data, $product): Product {
            $product->update($data->except('categories')->toArray());
            $product->categories()->sync($data->categories);
            $product->refresh();

            return $product;
        });
        ProductCacheInvalidated::dispatch($product->id);
        ProductAvailabilityCacheInvalidated::dispatch([$product->id]);
        ProductSearchIndexInvalidated::dispatch([$product->id]);

        return $product;
    }
}
