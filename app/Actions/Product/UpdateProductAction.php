<?php

declare(strict_types=1);

namespace App\Actions\Product;

use App\Data\Product\ProductUpdateData;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

final readonly class UpdateProductAction
{
    public function handle(ProductUpdateData $data, Product $product): Product
    {
        return DB::transaction(function () use ($data, $product): Product {
            $product->update($data->except('categories')->toArray());
            $product->categories()->sync($data->categories);
            $product->refresh();

            return $product;
        });
    }
}
