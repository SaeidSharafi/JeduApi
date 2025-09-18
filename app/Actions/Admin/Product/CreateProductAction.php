<?php

declare(strict_types=1);

namespace App\Actions\Admin\Product;

use App\Data\Admin\Product\ProductCreateData;
use App\Enums\ProductableEnum;
use App\Enums\PublicationStatusEnum;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

final readonly class CreateProductAction
{
    public function handle(ProductCreateData $data): Product
    {
        return DB::transaction(function () use ($data): Product {
            $forceCreate = $data->force_create ?? false;
            $productableClass = ProductableEnum::from($data->productable_type)->getModelClass();
            $productable = $productableClass::find($data->productable_id);
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
    }
}
