<?php

declare(strict_types=1);

namespace App\Actions\Admin\Product;

use App\Data\Admin\Product\ProductCreateData;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Product\ProductableEnum;
use App\Events\ProductAvailabilityCacheInvalidated;
use App\Events\ProductCacheInvalidated;
use App\Events\ProductSearchIndexInvalidated;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

final readonly class CreateProductAction
{
    public function handle(ProductCreateData $data): Product
    {
        $product = DB::transaction(function () use ($data): Product {
            $forceCreate      = $data->force_create ?? false;
            $productableClass = ProductableEnum::from($data->productable_type)->getModelClass();
            $productable      = $productableClass::find($data->productable_id);
            if ($forceCreate) {
                Product::query()
                    ->where('productable_id', $data->productable_id)
                    ->where('productable_type', $data->productable_type)
                    ->where('status', PublicationStatusEnum::PUBLISHED)
                    ->update(
                        ['status' => PublicationStatusEnum::ARCHIVED]
                    );
            }
            $product = Product::create([
                ...$data->except('force_create', 'categories')->toArray(),
                'slug' => $productable->slug,
            ])->fresh();
            $product->categories()->sync($data->categories);

            return $product;
        });
        ProductCacheInvalidated::dispatch($product->id);
        ProductAvailabilityCacheInvalidated::dispatch([$product->id]);
        ProductSearchIndexInvalidated::dispatch([$product->id]);

        return $product;
    }
}
